<?php

namespace Ernestdefoe\Dossier\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/** With Anthology enabled, a writer's dossier lists their series as each viewer may read them. */
class SeriesSectionTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-dossier', 'ernestdefoe-anthology');

        $now = Carbon::now();
        $chapter = fn (int $id, int $private = 0) => ['id' => $id, 'title' => "Chapter $id", 'user_id' => 3, 'created_at' => $now, 'comment_count' => 1, 'is_private' => $private];

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'writer', 'email' => 'writer@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'reader', 'email' => 'reader@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 4, 'group_id' => Group::MEMBER_ID],
            ],
            Discussion::class => [$chapter(1), $chapter(2), $chapter(3, 1), $chapter(4, 1)],
            'anthology_series' => [
                ['id' => 1, 'user_id' => 3, 'title' => 'The Lighthouse', 'slug' => 'the-lighthouse', 'status' => 'complete', 'follower_count' => 0, 'last_chapter_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'user_id' => 3, 'title' => 'Hidden Drafts', 'slug' => 'hidden-drafts', 'status' => 'ongoing', 'follower_count' => 0, 'last_chapter_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 3, 'user_id' => 4, 'title' => 'Someone Else', 'slug' => 'someone-else', 'status' => 'ongoing', 'follower_count' => 0, 'last_chapter_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ],
            'anthology_chapters' => [
                ['series_id' => 1, 'discussion_id' => 1, 'position' => 1, 'created_at' => $now],
                ['series_id' => 1, 'discussion_id' => 3, 'position' => 2, 'created_at' => $now],
                ['series_id' => 2, 'discussion_id' => 4, 'position' => 1, 'created_at' => $now],
                ['series_id' => 3, 'discussion_id' => 2, 'position' => 1, 'created_at' => $now],
            ],
        ]);
    }

    private function series(?int $as): ?array
    {
        $response = $this->send($this->request('GET', '/api/dossiers/3', $as ? ['authenticatedAs' => $as] : []));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true)['data']['series'];
    }

    #[Test]
    public function the_dossier_lists_only_series_and_chapters_the_viewer_can_read(): void
    {
        // Only series with a chapter the viewer can open, counting only those: the writer too,
        // since Dossier shows what Anthology would.
        foreach ([null, 4, 3] as $as) {
            $series = $this->series($as);
            $this->assertSame(['The Lighthouse'], array_column($series, 'title'));
            $this->assertSame(1, $series[0]['chapters']);
            $this->assertTrue($series[0]['complete']);
            $this->assertSame('the-lighthouse', $series[0]['slug']);
        }
    }

    #[Test]
    public function the_section_follows_its_visibility_and_anthologys_permission(): void
    {
        $this->send($this->request('PATCH', '/api/dossiers/3', ['authenticatedAs' => 3, 'json' => ['visibility' => ['series' => 'members']]]));
        $this->assertNull($this->series(null));
        $this->assertCount(1, $this->series(4));

        $this->database()->table('group_permission')->where('permission', 'anthology.view')->delete();
        $this->app()->getContainer()->make(\Flarum\Group\PermissionCache::class)->flush();
        $this->assertNull($this->series(4));
    }
}
