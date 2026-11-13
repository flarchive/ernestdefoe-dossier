<?php

namespace Ernestdefoe\Dossier\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;

/** With Garage enabled, a member's dossier lists the builds they finished, as each viewer may see them. */
class BuildsSectionTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-dossier', 'ernestdefoe-garage');

        $fields = [
            ['key' => 'year', 'label' => 'Year', 'type' => 'number', 'card' => true],
            ['key' => 'power', 'label' => 'Power', 'type' => 'number', 'unit' => 'hp', 'card' => true],
            ['key' => 'site', 'label' => 'Site', 'type' => 'link', 'card' => true],
            ['key' => 'colour', 'label' => 'Colour', 'type' => 'text', 'card' => false],
            ['key' => 'miles', 'label' => 'Mileage', 'type' => 'number', 'unit' => 'mi', 'card' => true],
            ['key' => 'engine', 'label' => 'Engine', 'type' => 'text', 'card' => true],
        ];

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'builder', 'email' => 'builder@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'viewer', 'email' => 'viewer@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 4, 'group_id' => Group::MEMBER_ID],
            ],
            'garage_categories' => [
                ['id' => 50, 'name' => 'Cars', 'slug' => 'cars', 'fields' => json_encode($fields), 'position' => 0, 'build_count' => 0],
            ],
            'garage_builds' => [
                $this->build(1, 'Older finished', finished: '2025-06-01', photos: [['url' => 'https://example.com/a.webp', 'small' => 'https://example.com/a-sm.webp']]),
                $this->build(2, 'Newest finished', finished: '2026-10-01', specs: ['year' => 1974, 'power' => 300, 'site' => 'https://example.com', 'colour' => 'Red', 'miles' => 125000, 'engine' => 'V8']),
                $this->build(3, 'Finished draft', finished: '2026-11-01', published: false),
                $this->build(4, 'Still in the shed', finished: null),
                $this->build(5, 'Someone else', finished: '2026-09-01', owner: 4),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function build(int $id, string $title, ?string $finished, bool $published = true, int $owner = 3, array $specs = [], array $photos = []): array
    {
        $now = Carbon::now();

        return [
            'id' => $id, 'user_id' => $owner, 'category_id' => 50, 'title' => $title, 'slug' => "build-$id",
            'stage' => $finished ? 'complete' : 'in_progress', 'specs' => json_encode($specs), 'photos' => json_encode($photos),
            'published_at' => $published ? $now : null, 'finished_at' => $finished, 'created_at' => $now, 'updated_at' => $now,
        ];
    }

    private function builds(?int $as): ?array
    {
        $response = $this->send($this->request('GET', '/api/dossiers/3', $as ? ['authenticatedAs' => $as] : []));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true)['data']['builds'];
    }

    #[Test]
    public function the_dossier_lists_only_published_finished_builds_newest_first(): void
    {
        // Guest, member, owner and admin alike: never a draft, even to its owner or an admin.
        foreach ([null, 4, 3, 1] as $as) {
            $this->assertSame(['Newest finished', 'Older finished'], array_column($this->builds($as), 'title'), 'as '.($as ?? 'guest'));
        }
    }

    #[Test]
    public function each_build_carries_its_cover_finish_date_and_card_specs(): void
    {
        [$newest, $older] = $this->builds(null);

        // Card fields in the category's order, links left out, at most three; five-digit numbers grouped.
        $this->assertSame(['1974', '300 hp', '125,000 mi'], $newest['specs']);
        $this->assertNull($newest['cover']);
        $this->assertSame('build-2', $newest['slug']);
        $this->assertSame('2026-10-01', $newest['finishedAt']);
        $this->assertSame('https://example.com/a-sm.webp', $older['cover']);
        $this->assertSame([], $older['specs']);
    }

    #[Test]
    public function the_section_follows_its_visibility_and_garages_permission(): void
    {
        $this->send($this->request('PATCH', '/api/dossiers/3', ['authenticatedAs' => 3, 'json' => ['visibility' => ['builds' => 'members']]]));
        $this->assertNull($this->builds(null));
        $this->assertCount(2, $this->builds(4));

        $this->database()->table('group_permission')->where('permission', 'ernestdefoe-garage.view')->delete();
        $this->app()->getContainer()->make(\Flarum\Group\PermissionCache::class)->flush();
        $this->assertNull($this->builds(4));
    }

    #[Test]
    public function reading_costs_the_same_queries_with_one_build_or_twenty(): void
    {
        $count = function (): int {
            $this->builds(4);
            $db = $this->app()->getContainer()->make(ConnectionInterface::class);
            $db->flushQueryLog();
            $db->enableQueryLog();
            $this->builds(4);
            $queries = count($db->getQueryLog());
            $db->disableQueryLog();

            return $queries;
        };

        $one = $count();

        for ($i = 10; $i < 30; $i++) {
            $this->database()->table('garage_builds')->insert($this->build($i, "Build $i", finished: '2024-01-01'));
        }

        $this->assertSame($one, $count());
        $this->assertCount(6, $this->builds(4));
    }
}
