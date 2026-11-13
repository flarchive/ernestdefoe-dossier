<?php

namespace Ernestdefoe\Dossier\Tests\integration\api;

use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class DossierTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const OWNER = 3;

    private const MEMBER = 4;

    private const MODERATOR = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-dossier');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'owner', 'email' => 'owner@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'member', 'email' => 'member@machine.local', 'is_email_confirmed' => 1],
                ['id' => 5, 'username' => 'moderator', 'email' => 'moderator@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 4, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 5, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 5, 'group_id' => Group::MODERATOR_ID],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /** @param array<string, mixed>|null $json */
    private function call(string $method, string $path, ?int $as = null, ?array $json = null): ResponseInterface
    {
        $options = [];

        if ($as) {
            $options['authenticatedAs'] = $as;
        }

        if ($json !== null) {
            $options['json'] = $json;
        }

        return $this->send($this->request($method, $path, $options));
    }

    /** @param array<string, mixed> $fields */
    private function add(string $section, array $fields, int $as = self::OWNER, int $owner = self::OWNER): ResponseInterface
    {
        return $this->call('POST', "/api/dossiers/$owner/entries", $as, ['section' => $section] + $fields);
    }

    private function dossier(?int $as, int $owner = self::OWNER): array
    {
        $response = $this->call('GET', "/api/dossiers/$owner", $as);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return $this->json($response)['data'];
    }

    /** A dossier with one of everything, every section at the visibility given. */
    private function fill(array $visibility = []): void
    {
        $response = $this->call('PATCH', '/api/dossiers/3', self::OWNER, [
            'headline' => 'Backend developer',
            'bio' => "Line one\nLine two <script>alert(1)</script>",
            'specializations' => 'APIs, Databases',
            'openTo' => ['mentoring', 'nonsense'],
            'visibility' => $visibility,
        ]);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $this->assertSame(201, $this->add('timeline', ['kind' => 'work', 'title' => 'Developer', 'start' => '2020-01', 'ongoing' => true])->getStatusCode());
        $this->assertSame(201, $this->add('skills', ['name' => 'PHP', 'level' => 90])->getStatusCode());
        $this->assertSame(201, $this->add('languages', ['name' => 'English', 'level' => 'C1', 'percent' => 90])->getStatusCode());
        $this->assertSame(201, $this->add('certificates', ['title' => 'AWS Developer', 'url' => 'https://aws.example.com/verify/1'])->getStatusCode());
        $this->assertSame(201, $this->add('links', ['kind' => 'github', 'url' => 'https://github.com/owner'])->getStatusCode());
    }

    #[Test]
    public function an_owner_fills_in_a_dossier_everyone_can_read(): void
    {
        $this->fill();
        $dossier = $this->dossier(null);

        $this->assertFalse($dossier['canEdit']);
        $this->assertArrayNotHasKey('visibility', $dossier);
        $this->assertSame('Backend developer', $dossier['about']['headline']);
        // Plain text, stored as typed: the page prints it as text, never as HTML.
        $this->assertSame("Line one\nLine two <script>alert(1)</script>", $dossier['about']['bio']);
        $this->assertSame(['APIs', 'Databases'], $dossier['about']['specializations']);
        // An open-to chip the admin does not offer is dropped.
        $this->assertSame(['mentoring'], array_column($dossier['openTo'], 'id'));
        // The test install has no language pack, so the chip's name is its key here (translated on a forum).
        $this->assertNotEmpty($dossier['openTo'][0]['label']);
        $this->assertSame('PHP', $dossier['skills'][0]['name']);
        $this->assertSame('Programming languages', $dossier['skills'][0]['category']);
        $this->assertNull($dossier['timeline'][0]['end']);
        $this->assertSame('fab fa-github', $dossier['links'][0]['icon']);
        $this->assertArrayNotHasKey('visibility', $dossier['links'][0]);

        $own = $this->dossier(self::OWNER);
        $this->assertTrue($own['canEdit']);
        $this->assertSame('everyone', $own['visibility']['skills']);
    }

    /** @return array<string, array{0: string, 1: bool, 2: bool, 3: bool}> */
    public static function levels(): array
    {
        // level => guest sees, member sees, owner sees
        return [
            'everyone' => ['everyone', true, true, true],
            'members' => ['members', false, true, true],
            'only me' => ['private', false, false, true],
        ];
    }

    #[Test]
    #[DataProvider('levels')]
    public function each_section_reaches_only_who_it_should(string $level, bool $guest, bool $member, bool $owner): void
    {
        $sections = ['about', 'timeline', 'skills', 'languages', 'projects', 'certificates', 'links', 'open_to'];
        $this->fill(array_fill_keys($sections, $level));

        foreach ([[null, $guest], [self::MEMBER, $member], [self::OWNER, $owner]] as [$as, $sees]) {
            $dossier = $this->dossier($as);
            $body = json_encode($dossier);

            foreach (['about', 'timeline', 'skills', 'languages', 'certificates', 'links', 'openTo'] as $key) {
                $this->assertSame($sees, $dossier[$key] !== null && $dossier[$key] !== [], "$key for viewer ".($as ?? 'guest')." at $level");
            }

            // Nothing hidden rides along anywhere in the answer.
            if (! $sees) {
                foreach (['Backend developer', 'Developer', 'PHP', 'English', 'AWS Developer', 'github.com/owner', 'Mentoring'] as $secret) {
                    $this->assertStringNotContainsString($secret, (string) $body, "$secret leaked to ".($as ?? 'guest'));
                }
            }
        }
    }

    #[Test]
    public function a_link_narrower_than_its_section_is_left_out(): void
    {
        $this->add('links', ['kind' => 'website', 'url' => 'https://public.example.com']);
        $this->add('links', ['kind' => 'linkedin', 'url' => 'https://linkedin.com/in/owner', 'visibility' => 'members']);
        $this->add('links', ['kind' => 'custom', 'url' => 'https://secret.example.com', 'label' => 'Secret', 'visibility' => 'private']);

        $this->assertSame(['https://public.example.com'], array_column($this->dossier(null)['links'], 'url'));
        $this->assertSame(['https://public.example.com', 'https://linkedin.com/in/owner'], array_column($this->dossier(self::MEMBER)['links'], 'url'));
        $this->assertCount(3, $this->dossier(self::OWNER)['links']);
        $this->assertCount(3, $this->dossier(self::MODERATOR)['links'], 'Edit any dossier sees what it edits');
    }

    #[Test]
    public function the_profile_says_whether_this_viewer_finds_anything(): void
    {
        $hasDossier = fn (?int $as) => $this->json($this->call('GET', '/api/users/3', $as))['data']['attributes']['hasDossier'] ?? 'missing';

        $this->assertFalse($hasDossier(null));
        $this->assertFalse($hasDossier(self::OWNER));

        $this->call('PATCH', '/api/dossiers/3', self::OWNER, ['headline' => 'Hello', 'visibility' => ['about' => 'members']]);
        $this->assertFalse($hasDossier(null));
        $this->assertTrue($hasDossier(self::MEMBER));

        $this->call('PATCH', '/api/dossiers/3', self::OWNER, ['visibility' => ['about' => 'private']]);
        $this->assertFalse($hasDossier(self::MEMBER));
        $this->assertTrue($hasDossier(self::OWNER));

        $this->call('PATCH', '/api/dossiers/3', self::OWNER, ['visibility' => ['about' => 'everyone']]);
        $this->assertTrue($hasDossier(null));

        // Never in the user list, nor on users included with discussions and posts.
        $list = $this->json($this->call('GET', '/api/users', 1));
        foreach ($list['data'] as $user) {
            $this->assertArrayNotHasKey('hasDossier', $user['attributes']);
        }
    }

    #[Test]
    public function members_cannot_write_to_someone_elses_dossier(): void
    {
        $this->fill();
        $entry = $this->dossier(self::OWNER)['timeline'][0]['id'];

        $this->assertSame(403, $this->call('PATCH', '/api/dossiers/3', self::MEMBER, ['headline' => 'Hacked'])->getStatusCode());
        $this->assertSame(403, $this->add('timeline', ['kind' => 'work', 'title' => 'Hacked', 'start' => '2020-01', 'ongoing' => true], self::MEMBER)->getStatusCode());
        $this->assertSame(403, $this->call('PATCH', "/api/dossiers/3/entries/$entry", self::MEMBER, ['title' => 'Hacked'])->getStatusCode());
        $this->assertSame(403, $this->call('DELETE', "/api/dossiers/3/entries/$entry", self::MEMBER)->getStatusCode());
        $this->assertSame(403, $this->call('POST', '/api/dossiers/3/order', self::MEMBER, ['section' => 'timeline', 'ids' => [$entry]])->getStatusCode());
        $this->assertContains($this->call('PATCH', '/api/dossiers/3', null, ['headline' => 'Hacked'])->getStatusCode(), [400, 401]);

        // The owner's entry id through the member's own dossier: not theirs, so not found.
        $this->assertSame(404, $this->call('PATCH', "/api/dossiers/4/entries/$entry", self::MEMBER, ['kind' => 'work', 'title' => 'Hacked', 'start' => '2020-01', 'ongoing' => true])->getStatusCode());
        $this->assertSame(404, $this->call('DELETE', "/api/dossiers/4/entries/$entry", self::MEMBER)->getStatusCode());

        $this->assertSame('Developer', $this->dossier(self::OWNER)['timeline'][0]['title']);
        $this->assertSame('Backend developer', $this->dossier(self::OWNER)['about']['headline']);
    }

    #[Test]
    public function a_body_cannot_move_an_entry_to_another_member(): void
    {
        $response = $this->call('POST', '/api/dossiers/4/entries', self::MEMBER, [
            'section' => 'languages', 'name' => 'French', 'level' => 'B2', 'user_id' => 3, 'userId' => 3, 'position' => 99, 'image' => '../../config.php',
        ]);
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        $row = $this->database()->table('dossier_entries')->where('section', 'languages')->first();
        $this->assertSame(4, (int) $row->user_id);
        $this->assertSame(1, (int) $row->position);
        $this->assertNull($row->image);
        $this->assertSame([], $this->dossier(self::OWNER)['languages']);
    }

    #[Test]
    public function moderators_with_edit_any_can_edit_others_and_members_without_have_cannot(): void
    {
        $this->assertSame(200, $this->call('PATCH', '/api/dossiers/3', self::MODERATOR, ['headline' => 'Set by a moderator'])->getStatusCode());
        $this->assertSame('Set by a moderator', $this->dossier(null)['about']['headline']);

        $this->revoke('ernestdefoe-dossier.have');
        $this->assertSame(403, $this->call('PATCH', '/api/dossiers/4', self::MEMBER, ['headline' => 'Mine'])->getStatusCode());
        $this->assertFalse($this->dossier(self::MEMBER, self::MEMBER)['canEdit']);
        // A moderator keeps "Edit any dossier" (and admins can do everything).
        $this->assertSame(200, $this->call('PATCH', '/api/dossiers/4', self::MODERATOR, ['headline' => 'Fixed'])->getStatusCode());
    }

    #[Test]
    public function without_view_permission_a_dossier_is_closed_except_to_its_owner(): void
    {
        $this->fill();
        $this->revoke('ernestdefoe-dossier.view');

        $this->assertSame(403, $this->call('GET', '/api/dossiers/3')->getStatusCode());
        $this->assertSame(403, $this->call('GET', '/api/dossiers/3', self::MEMBER)->getStatusCode());
        $this->assertSame(200, $this->call('GET', '/api/dossiers/3', self::OWNER)->getStatusCode());
        $this->assertSame(200, $this->call('GET', '/api/dossiers/3', self::MODERATOR)->getStatusCode());
    }

    /** @return array<string, array{0: string}> */
    public static function badUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'control character first' => ["\x01javascript:alert(1)"],
            'tab inside the scheme' => ["java\tscript:alert(1)"],
            'zero-width space' => ["\u{200B}javascript:alert(1)"],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
            'ftp' => ['ftp://example.com/file'],
            'protocol-relative' => ['//evil.example.com'],
            'no host' => ['https://'],
            'quote breaks out' => ['https://example.com/"onmouseover="alert(1)'],
        ];
    }

    #[Test]
    #[DataProvider('badUrls')]
    public function link_and_certificate_urls_must_be_web_addresses(string $url): void
    {
        $this->assertSame(422, $this->add('links', ['kind' => 'website', 'url' => $url])->getStatusCode());
        $this->assertSame(422, $this->add('certificates', ['title' => 'Cert', 'url' => $url])->getStatusCode());
        $this->assertSame(0, $this->database()->table('dossier_entries')->count());
    }

    #[Test]
    public function control_characters_are_stripped_from_good_urls(): void
    {
        $this->assertSame(201, $this->add('links', ['kind' => 'website', 'url' => "  https://exa\u{200B}mple.com/\x01me  "])->getStatusCode());
        $this->assertSame('https://example.com/me', $this->dossier(null)['links'][0]['url']);
    }

    #[Test]
    public function entries_are_checked_per_section(): void
    {
        $this->assertSame(422, $this->add('timeline', ['kind' => 'work', 'title' => 'X', 'start' => '2022-05', 'end' => '2021-01'])->getStatusCode());
        $this->assertSame(422, $this->add('timeline', ['kind' => 'job', 'title' => 'X', 'start' => '2022-05', 'ongoing' => true])->getStatusCode());
        $this->assertSame(422, $this->add('timeline', ['kind' => 'work', 'title' => 'X', 'start' => '2022-13', 'ongoing' => true])->getStatusCode());
        $this->assertSame(422, $this->add('languages', ['name' => 'English', 'level' => 'C3'])->getStatusCode());
        $this->assertSame(422, $this->add('skills', ['name' => 'PHP', 'level' => 101])->getStatusCode());
        $this->assertSame(422, $this->add('nonsense', ['title' => 'X'])->getStatusCode());
        $this->assertSame(201, $this->add('skills', ['name' => 'php', 'level' => 50])->getStatusCode());
        // "php" is the catalogue's PHP, and a skill is listed once.
        $this->assertSame(422, $this->add('skills', ['name' => 'PHP ', 'level' => 60])->getStatusCode());
        $this->assertSame(0, $this->database()->table('dossier_skills')->where('approved', false)->count());
    }

    #[Test]
    public function typed_skills_wait_for_an_admin_and_can_be_switched_off(): void
    {
        $this->assertSame(201, $this->add('skills', ['name' => 'Elixir', 'level' => 40])->getStatusCode());
        $skill = $this->database()->table('dossier_skills')->where('slug', 'elixir')->first();
        $this->assertSame(0, (int) $skill->approved);

        // Another member is not offered it, and cannot pick it by id.
        $catalogue = $this->json($this->call('GET', '/api/dossier-catalogue', self::MEMBER))['data'];
        $this->assertNotContains('Elixir', array_column($catalogue['skills'], 'name'));
        $this->assertSame(422, $this->add('skills', ['skillId' => $skill->id, 'level' => 40], self::MEMBER, self::MEMBER)->getStatusCode());

        // Admins see it with its use count and add it to the catalogue.
        $manage = $this->json($this->send($this->request('GET', '/api/dossier-catalogue', ['authenticatedAs' => 1])->withQueryParams(['manage' => '1'])))['data'];
        $pending = array_values(array_filter($manage['skills'], fn ($s) => ! $s['approved']));
        $this->assertSame([['id' => $skill->id, 'name' => 'Elixir', 'approved' => false, 'uses' => 1]], $pending);
        $this->assertSame(403, $this->send($this->request('GET', '/api/dossier-catalogue', ['authenticatedAs' => self::MEMBER])->withQueryParams(['manage' => '1']))->getStatusCode());
        $this->assertSame(403, $this->call('PATCH', "/api/dossier-catalogue/skills/$skill->id", self::MEMBER, ['approved' => true])->getStatusCode());
        $this->assertSame(200, $this->call('PATCH', "/api/dossier-catalogue/skills/$skill->id", 1, ['approved' => true, 'categoryId' => 2])->getStatusCode());
        $this->assertSame(201, $this->add('skills', ['skillId' => $skill->id, 'level' => 40], self::MEMBER, self::MEMBER)->getStatusCode());

        $this->putSetting('ernestdefoe-dossier.custom_skills', '0');
        $this->assertSame(422, $this->add('skills', ['name' => 'Haskell', 'level' => 10])->getStatusCode());
    }

    #[Test]
    public function an_unused_typed_skill_is_removed_with_its_last_entry(): void
    {
        $this->add('skills', ['name' => 'Zig', 'level' => 20]);
        $entry = $this->dossier(self::OWNER)['skills'][0]['id'];
        $this->assertSame(200, $this->call('DELETE', "/api/dossiers/3/entries/$entry", self::OWNER)->getStatusCode());
        $this->assertSame(0, $this->database()->table('dossier_skills')->where('slug', 'zig')->count());
    }

    #[Test]
    public function entries_can_be_reordered_only_within_the_dossier(): void
    {
        foreach (['English', 'French', 'German'] as $name) {
            $this->add('languages', ['name' => $name, 'level' => 'B1']);
        }
        $this->add('languages', ['name' => 'Spanish', 'level' => 'A1'], self::MEMBER, self::MEMBER);
        $ids = array_column($this->dossier(self::OWNER)['languages'], 'id');
        $theirs = $this->dossier(self::MEMBER, self::MEMBER)['languages'][0]['id'];

        $response = $this->call('POST', '/api/dossiers/3/order', self::OWNER, ['section' => 'languages', 'ids' => [$ids[2], $theirs, $ids[0], $ids[1]]]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['German', 'English', 'French'], array_column($this->json($response)['data']['languages'], 'name'));
        $this->assertSame(['Spanish'], array_column($this->dossier(self::MEMBER, self::MEMBER)['languages'], 'name'));
    }

    #[Test]
    public function certificate_pictures_are_reencoded_as_webp(): void
    {
        $this->add('certificates', ['title' => 'Cert']);
        $entry = $this->dossier(self::OWNER)['certificates'][0]['id'];

        $png = tempnam(sys_get_temp_dir(), 'dossier');
        $image = imagecreatetruecolor(40, 30);
        imagepng($image, $png);

        $upload = fn (int $as, int $owner) => $this->send($this->request('POST', "/api/dossiers/$owner/entries/$entry/image", ['authenticatedAs' => $as])
            ->withUploadedFiles(['image' => new UploadedFile($png, (int) filesize($png), UPLOAD_ERR_OK, 'cert.png', 'image/png')]));

        $this->assertSame(403, $upload(self::MEMBER, self::OWNER)->getStatusCode());
        $this->assertSame(404, $upload(self::MEMBER, self::MEMBER)->getStatusCode());

        $response = $upload(self::OWNER, self::OWNER);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $image = $this->json($response)['data']['certificates'][0]['image'];
        $this->assertMatchesRegularExpression('#/assets/dossier/certificates/[A-Za-z0-9]{32}\.webp$#', $image);

        // Not an image at all.
        $fake = tempnam(sys_get_temp_dir(), 'dossier');
        file_put_contents($fake, '<?php echo 1;');
        $response = $this->send($this->request('POST', "/api/dossiers/3/entries/$entry/image", ['authenticatedAs' => self::OWNER])
            ->withUploadedFiles(['image' => new UploadedFile($fake, (int) filesize($fake), UPLOAD_ERR_OK, 'cert.png', 'image/png')]));
        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function deleting_a_member_deletes_their_pictures(): void
    {
        $this->add('certificates', ['title' => 'Cert']);
        $entry = $this->dossier(self::OWNER)['certificates'][0]['id'];
        $png = tempnam(sys_get_temp_dir(), 'dossier');
        imagepng(imagecreatetruecolor(20, 20), $png);
        $this->send($this->request('POST', "/api/dossiers/3/entries/$entry/image", ['authenticatedAs' => self::OWNER])
            ->withUploadedFiles(['image' => new UploadedFile($png, (int) filesize($png), UPLOAD_ERR_OK, 'c.png', 'image/png')]));
        $stored = $this->database()->table('dossier_entries')->where('id', $entry)->value('image');
        $disk = $this->app()->getContainer()->make(\Illuminate\Contracts\Filesystem\Factory::class)->disk('ernestdefoe-dossier');
        $this->assertTrue($disk->exists($stored));

        $this->assertSame(204, $this->call('DELETE', '/api/users/3', 1)->getStatusCode());
        $this->assertFalse($disk->exists($stored));
        $this->assertSame(0, $this->database()->table('dossier_entries')->where('user_id', 3)->count());
    }

    #[Test]
    public function admins_manage_categories_and_a_deleted_category_keeps_its_skills(): void
    {
        $this->assertSame(403, $this->call('POST', '/api/dossier-catalogue/categories', self::MODERATOR, ['name' => 'Ops'])->getStatusCode());
        $response = $this->call('POST', '/api/dossier-catalogue/categories', 1, ['name' => 'Ops']);
        $this->assertSame(201, $response->getStatusCode());
        $category = $this->json($response)['data']['id'];
        $this->assertSame(201, $this->call('POST', '/api/dossier-catalogue/skills', 1, ['name' => 'Kubernetes', 'categoryId' => $category])->getStatusCode());
        $this->assertSame(422, $this->call('POST', '/api/dossier-catalogue/skills', 1, ['name' => 'kubernetes'])->getStatusCode());
        $this->assertSame(204, $this->call('DELETE', "/api/dossier-catalogue/categories/$category", 1)->getStatusCode());
        $this->assertNull($this->database()->table('dossier_skills')->where('slug', 'kubernetes')->value('category_id'));

        // The admin lists it under "No category" (Discuss d/40301 #5: that block broke the settings page).
        $catalogue = $this->json($this->call('GET', '/api/dossier-catalogue?manage=1', 1))['data'];
        $skill = collect($catalogue['skills'])->firstWhere('name', 'Kubernetes');
        $this->assertTrue($skill['approved']);
        $this->assertNull($skill['categoryId']);
    }

    #[Test]
    public function reading_a_dossier_costs_the_same_queries_with_one_entry_or_many(): void
    {
        $count = function (): int {
            // A first call so the once-a-minute "last seen" update is not counted.
            $this->call('GET', '/api/dossiers/3', self::MEMBER);
            $db = $this->app()->getContainer()->make(ConnectionInterface::class);
            $db->flushQueryLog();
            $db->enableQueryLog();
            $this->assertSame(200, $this->call('GET', '/api/dossiers/3', self::MEMBER)->getStatusCode());
            $queries = count($db->getQueryLog());
            $db->disableQueryLog();

            return $queries;
        };

        $this->fill();
        $one = $count();

        for ($i = 0; $i < 15; $i++) {
            $this->add('timeline', ['kind' => $i % 2 ? 'work' : 'education', 'title' => "Job $i", 'start' => '2010-01', 'end' => '2012-01']);
            $this->add('skills', ['name' => "Skill $i", 'level' => 50]);
            $this->add('languages', ['name' => "Language $i", 'level' => 'B1']);
            $this->add('certificates', ['title' => "Cert $i"]);
            $this->add('links', ['kind' => 'custom', 'url' => "https://example.com/$i"]);
        }

        $this->assertSame($one, $count());
        // The dossier itself: the member, the dossier row, its entries, the skills they name.
        $this->assertLessThanOrEqual(10, $one);
    }

    private function revoke(string $permission): void
    {
        $this->database()->table('group_permission')->where('permission', $permission)->delete();
        $this->app()->getContainer()->make(\Flarum\Group\PermissionCache::class)->flush();
    }

    private function putSetting(string $key, string $value): void
    {
        $this->app()->getContainer()->make(\Flarum\Settings\SettingsRepositoryInterface::class)->set($key, $value);
    }
}
