<?php

namespace Ernestdefoe\Dossier\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/** With Syllabus enabled, a dossier lists the member's course certificates and marks the skills they earned. */
class SyllabusSectionTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-dossier', 'ernestdefoe-syllabus');
        $this->setting('forum_title', 'Test Forum');

        $now = Carbon::now();
        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'learner', 'email' => 'learner@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'viewer', 'email' => 'viewer@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 4, 'group_id' => Group::MEMBER_ID],
            ],
            'syllabus_courses' => [
                ['id' => 10, 'title' => 'Photogrammetry basics', 'slug' => 'photogrammetry-basics', 'status' => 'published', 'skills' => json_encode(['Photogrammetry', 'RealityCapture']), 'created_at' => $now, 'updated_at' => $now],
                ['id' => 11, 'title' => 'Unlisted workshop', 'slug' => 'unlisted-workshop', 'status' => 'draft', 'skills' => json_encode(['Secret sauce']), 'created_at' => $now, 'updated_at' => $now],
            ],
            'syllabus_certificates' => [
                $this->certificate(1, 10, 'aaa111', 'Photogrammetry basics', '2026-11-01 10:00:00', ['Photogrammetry', 'RealityCapture']),
                $this->certificate(2, 11, 'bbb222', 'Unlisted workshop', '2026-10-01 10:00:00', ['Secret sauce']),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function certificate(int $id, int $course, string $code, string $title, string $issued, array $skills, int $user = 3): array
    {
        return ['id' => $id, 'course_id' => $course, 'user_id' => $user, 'code' => $code, 'course_title' => $title, 'user_name' => 'learner', 'issued_at' => $issued, 'skills' => json_encode($skills)];
    }

    /** @return array<string, mixed> */
    private function dossier(?int $as): array
    {
        $response = $this->send($this->request('GET', '/api/dossiers/3', $as ? ['authenticatedAs' => $as] : []));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true)['data'];
    }

    private function patch(array $json, int $as = 3): ResponseInterface
    {
        return $this->send($this->request('PATCH', '/api/dossiers/3', ['authenticatedAs' => $as, 'json' => $json]));
    }

    private function addSkill(string $name, int $as = 3): ResponseInterface
    {
        return $this->send($this->request('POST', '/api/dossiers/3/entries', ['authenticatedAs' => $as, 'json' => ['section' => 'skills', 'name' => $name, 'level' => 60]]));
    }

    /** @return list<array<string, mixed>> */
    private function courseCertificates(?int $as): array
    {
        return array_values(array_filter($this->dossier($as)['certificates'], fn ($c) => ! empty($c['syllabus'])));
    }

    #[Test]
    public function course_certificates_follow_the_members_own_as_syllabus_lets_each_viewer_see_them(): void
    {
        $this->send($this->request('POST', '/api/dossiers/3/entries', ['authenticatedAs' => 3, 'json' => ['section' => 'certificates', 'title' => 'AWS Developer', 'issued' => '2025-04']]));

        // Others see only certificates of courses they can open (the draft course's stays out).
        foreach ([null, 4] as $as) {
            $certificates = $this->dossier($as)['certificates'];
            $this->assertSame(['AWS Developer', 'Photogrammetry basics'], array_column($certificates, 'title'), 'as '.($as ?? 'guest'));
            $course = $certificates[1];
            $this->assertTrue($course['syllabus']);
            $this->assertSame('syllabus-aaa111', $course['id']);
            $this->assertSame('Test Forum', $course['issuer']);
            $this->assertSame('2026-11', $course['issued']);
            $this->assertStringEndsWith('/certificates/aaa111', $course['url']);
            $this->assertArrayNotHasKey('hidden', $course);
        }

        // The member sees all of theirs, newest first.
        $this->assertSame(['aaa111', 'bbb222'], array_column($this->courseCertificates(3), 'code'));
        $this->assertSame([false, false], array_column($this->courseCertificates(3), 'hidden'));
    }

    private function toggle(string $code, bool $hidden, int $as = 3): ResponseInterface
    {
        return $this->patch(['certificate' => ['code' => $code, 'hidden' => $hidden]], $as);
    }

    private function storedHidden(): ?array
    {
        $codes = $this->database()->table('dossiers')->where('user_id', 3)->value('hidden_certificates');

        return $codes === null ? null : json_decode($codes, true);
    }

    #[Test]
    public function a_member_can_hide_a_course_certificate_and_show_it_again(): void
    {
        $this->assertSame(200, $this->toggle('aaa111', true)->getStatusCode());
        $this->addSkill('Photogrammetry');

        foreach ([null, 4] as $as) {
            $this->assertSame([], $this->courseCertificates($as));
            // A hidden certificate no longer vouches for its skills either.
            $this->assertNull($this->dossier($as)['skills'][0]['verified']);
        }
        $this->assertSame([true, false], array_column($this->courseCertificates(3), 'hidden'));

        // One code and a boolean; someone else can't hide them.
        $this->assertSame(422, $this->patch(['certificate' => ['code' => 'no spaces', 'hidden' => true]])->getStatusCode());
        $this->assertSame(422, $this->patch(['certificate' => ['code' => 'aaa111', 'hidden' => 'yes']])->getStatusCode());
        $this->assertSame(422, $this->patch(['certificate' => 'aaa111'])->getStatusCode());
        $this->assertSame(403, $this->toggle('aaa111', false, 4)->getStatusCode());

        $this->assertSame(200, $this->toggle('aaa111', false)->getStatusCode());
        $this->assertSame(['aaa111'], array_column($this->courseCertificates(null), 'code'));
    }

    #[Test]
    public function showing_one_certificate_leaves_the_others_hidden(): void
    {
        // Each toggle names one code and is merged on the server, so an editor who sees
        // fewer certificates than the member can't un-hide the ones they don't see.
        $this->toggle('aaa111', true);
        $this->toggle('bbb222', true);
        $this->toggle('aaa111', true);
        $this->assertSame(['bbb222', 'aaa111'], $this->storedHidden());

        $this->toggle('aaa111', false);
        $this->assertSame(['bbb222'], $this->storedHidden());
        $this->toggle('bbb222', false);
        $this->assertNull($this->storedHidden());
    }

    #[Test]
    public function a_badge_shows_only_to_whoever_may_see_the_certificates(): void
    {
        $this->addSkill('Photogrammetry');
        $this->patch(['visibility' => ['certificates' => 'members']]);

        $this->assertNull($this->dossier(null)['certificates']);
        $this->assertNull($this->dossier(null)['skills'][0]['verified']);
        $this->assertSame(['Photogrammetry basics'], $this->dossier(4)['skills'][0]['verified']);
    }

    #[Test]
    public function skills_a_course_earned_are_verified_and_skills_picked_by_hand_are_not(): void
    {
        $this->addSkill('photogrammetry');
        $this->addSkill('Cooking');

        $skills = array_column($this->dossier(null)['skills'], 'verified', 'name');
        $this->assertSame(['Photogrammetry basics'], $skills['photogrammetry']);
        $this->assertNull($skills['Cooking']);

        // The skill window: everything the member earned, once each, with its course; only for the editor.
        $earned = $this->dossier(3)['earnedSkills'];
        $this->assertSame(['Photogrammetry', 'RealityCapture', 'Secret sauce'], array_column($earned, 'name'));
        $this->assertSame(['Unlisted workshop'], $earned[2]['courses']);
        $this->assertArrayNotHasKey('earnedSkills', $this->dossier(4));
    }

    #[Test]
    public function a_member_who_keeps_their_courses_private_shows_no_course_certificates_or_badges(): void
    {
        $this->addSkill('Photogrammetry');
        $this->database()->table('users')->where('id', 3)->update(['preferences' => json_encode(['syllabusShowCourses' => false])]);

        foreach ([null, 4] as $as) {
            $this->assertSame([], $this->courseCertificates($as));
            $this->assertNull($this->dossier($as)['skills'][0]['verified']);
        }
        $this->assertCount(2, $this->courseCertificates(3));
    }

    #[Test]
    public function with_typed_skills_off_a_member_can_still_add_a_skill_a_course_earned(): void
    {
        $this->app();
        $this->app()->getContainer()->make(\Flarum\Settings\SettingsRepositoryInterface::class)->set('ernestdefoe-dossier.custom_skills', false);

        $this->assertSame(201, $this->addSkill('RealityCapture')->getStatusCode());
        $this->assertSame(422, $this->addSkill('Knitting')->getStatusCode());
    }

    #[Test]
    public function reading_costs_the_same_queries_with_one_certificate_or_twenty(): void
    {
        $this->database()->table('syllabus_certificates')->where('id', 2)->delete();
        $this->addSkill('Photogrammetry');

        $count = function (?int $as): int {
            $this->dossier($as);
            $db = $this->app()->getContainer()->make(ConnectionInterface::class);
            $db->flushQueryLog();
            $db->enableQueryLog();
            $this->dossier($as);
            $queries = count($db->getQueryLog());
            $db->disableQueryLog();

            return $queries;
        };

        $one = array_map($count, [null, 4, 3]);

        for ($i = 100; $i < 119; $i++) {
            $this->database()->table('syllabus_certificates')->insert($this->certificate($i, 10, "code$i", "Course $i", '2026-01-01 10:00:00', ["Skill $i"]));
        }

        $this->assertSame($one, array_map($count, [null, 4, 3]));
        $this->assertCount(20, $this->courseCertificates(null));
    }
}
