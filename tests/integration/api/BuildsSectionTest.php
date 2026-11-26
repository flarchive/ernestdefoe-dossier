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
        return $this->dossier($as)['builds'];
    }

    /** @return array<string, mixed> */
    private function dossier(?int $as): array
    {
        $response = $this->send($this->request('GET', '/api/dossiers/3', $as ? ['authenticatedAs' => $as] : []));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true)['data'];
    }

    private function pin(array $ids, int $as = 3): \Psr\Http\Message\ResponseInterface
    {
        return $this->send($this->request('PATCH', '/api/dossiers/3', ['authenticatedAs' => $as, 'json' => ['pinnedBuilds' => $ids]]));
    }

    private function changeSetting(string $key, mixed $value): void
    {
        $this->app()->getContainer()->make(\Flarum\Settings\SettingsRepositoryInterface::class)->set($key, $value);
    }

    private function storedPins(): ?array
    {
        $pins = $this->database()->table('dossiers')->where('user_id', 3)->value('pinned_builds');

        return $pins === null ? null : json_decode($pins, true);
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
    public function reading_costs_the_same_queries_with_two_builds_or_twenty_two(): void
    {
        // Pinned builds and the newest ones come from the same query.
        $this->assertSame(200, $this->pin([1])->getStatusCode());

        $count = function (?int $as): int {
            $this->builds($as);
            $db = $this->app()->getContainer()->make(ConnectionInterface::class);
            $db->flushQueryLog();
            $db->enableQueryLog();
            $this->builds($as);
            $queries = count($db->getQueryLog());
            $db->disableQueryLog();

            return $queries;
        };

        $before = array_map($count, [null, 4, 3]);

        for ($i = 10; $i < 30; $i++) {
            $this->database()->table('garage_builds')->insert($this->build($i, "Build $i", finished: '2024-01-01'));
        }

        $this->assertSame($before, array_map($count, [null, 4, 3]));
        $this->assertCount(6, $this->builds(4));
        $this->assertSame('Older finished', $this->builds(null)[0]['title']);
        $this->assertCount(22, $this->dossier(3)['buildChoices']);
    }

    #[Test]
    public function pinned_builds_come_first_in_the_members_order_then_the_newest(): void
    {
        for ($i = 10; $i < 14; $i++) {
            $this->database()->table('garage_builds')->insert($this->build($i, "Build $i", finished: '2026-01-01'));
        }

        $this->assertSame(200, $this->pin([10, 1])->getStatusCode());
        $this->assertSame([10, 1], $this->storedPins());

        foreach ([null, 4, 3, 1] as $as) {
            $builds = $this->builds($as);
            $this->assertSame(['Build 10', 'Older finished', 'Newest finished'], array_slice(array_column($builds, 'title'), 0, 3), 'as '.($as ?? 'guest'));
            $this->assertSame([true, true, false], array_slice(array_column($builds, 'pinned'), 0, 3));
        }

        // Only an editor gets the pin window's list and the limit.
        $this->assertArrayNotHasKey('buildChoices', $this->dossier(4));
        $owner = $this->dossier(3);
        $this->assertSame(3, $owner['pinLimit']);
        $this->assertSame([10, 1], array_column(array_filter($owner['buildChoices'], fn ($b) => $b['pinned']), 'id'));
    }

    #[Test]
    public function a_save_keeps_only_the_members_own_published_finished_builds(): void
    {
        // A draft, an unfinished build, someone else's and one that doesn't exist all drop out.
        $this->assertSame(200, $this->pin([3, 4, 1])->getStatusCode());
        $this->assertSame([1], $this->storedPins());
        $this->assertSame(200, $this->pin([5, 999, '2'])->getStatusCode());
        $this->assertSame([2], $this->storedPins());

        // More than the admin allows, or not a list of ids, is refused.
        $this->assertSame(422, $this->pin([1, 2, 3, 4])->getStatusCode());
        $this->assertSame(422, $this->pin(['x'])->getStatusCode());
        $this->assertSame(422, $this->pin([[1]])->getStatusCode());
        $this->assertSame([2], $this->storedPins());

        // Someone else can't pin on this dossier.
        $this->assertSame(403, $this->pin([1], 4)->getStatusCode());

        $this->assertSame(200, $this->pin([])->getStatusCode());
        $this->assertNull($this->storedPins());
    }

    #[Test]
    public function a_pin_on_a_build_no_longer_published_or_finished_drops_out(): void
    {
        $this->assertSame(200, $this->pin([1, 2])->getStatusCode());

        // Written straight to the table, past the save's checks: a draft and someone else's build.
        $this->database()->table('dossiers')->where('user_id', 3)->update(['pinned_builds' => json_encode([3, 5, 1])]);
        foreach ([null, 4, 3, 1] as $as) {
            $this->assertSame(['Older finished', 'Newest finished'], array_column($this->builds($as), 'title'), 'as '.($as ?? 'guest'));
        }

        // Build 1 goes back to the shed: gone from the list, and from the pins on the next save of anything.
        $this->database()->table('garage_builds')->where('id', 1)->update(['finished_at' => null, 'stage' => 'in_progress']);
        $this->assertSame(['Newest finished'], array_column($this->builds(null), 'title'));
        $this->send($this->request('PATCH', '/api/dossiers/3', ['authenticatedAs' => 3, 'json' => ['headline' => 'Builder']]));
        $this->assertNull($this->storedPins());
    }

    #[Test]
    public function the_admin_limit_caps_the_pins_and_zero_turns_them_off(): void
    {
        $this->assertSame(200, $this->pin([1, 2])->getStatusCode());

        $this->changeSetting('ernestdefoe-dossier.pinned_builds', 1);
        $this->assertSame([true, false], array_column($this->builds(null), 'pinned'));
        $this->assertSame(1, $this->dossier(3)['pinLimit']);
        $this->assertSame(422, $this->pin([1, 2])->getStatusCode());

        $this->changeSetting('ernestdefoe-dossier.pinned_builds', 0);
        $this->assertSame(['Newest finished', 'Older finished'], array_column($this->builds(null), 'title'));
        $this->assertSame([false, false], array_column($this->builds(null), 'pinned'));
    }

    #[Test]
    public function a_guest_who_may_not_see_builds_gets_none_pinned_or_not(): void
    {
        $this->assertSame(200, $this->pin([1])->getStatusCode());
        // Garage grants viewing to guests (and so everyone); move it to members only.
        $this->database()->table('group_permission')->where('permission', 'ernestdefoe-garage.view')->delete();
        $this->database()->table('group_permission')->insert(['permission' => 'ernestdefoe-garage.view', 'group_id' => Group::MEMBER_ID]);
        $this->app()->getContainer()->make(\Flarum\Group\PermissionCache::class)->flush();

        $this->assertNull($this->builds(null));
        $this->assertSame('Older finished', $this->builds(4)[0]['title']);
    }

    /** Member 3 on member 4's builds: accepted with and without a role, and a pending invite. */
    private function joinTeams(): void
    {
        $now = Carbon::now();
        $this->database()->table('garage_builds')->insert([
            $this->build(6, 'Team rig', finished: '2026-08-01', owner: 4),
            $this->build(7, 'Invited, not accepted', finished: '2026-07-01', owner: 4),
            $this->build(8, 'Team draft', finished: '2026-07-15', published: false, owner: 4),
        ]);
        $this->database()->table('garage_members')->insert([
            ['build_id' => 5, 'user_id' => 3, 'role' => null, 'status' => 'accepted', 'created_at' => $now, 'accepted_at' => $now],
            ['build_id' => 6, 'user_id' => 3, 'role' => 'Backend developer', 'status' => 'accepted', 'created_at' => $now, 'accepted_at' => $now],
            ['build_id' => 7, 'user_id' => 3, 'role' => 'Tester', 'status' => 'pending', 'created_at' => $now, 'accepted_at' => null],
            ['build_id' => 8, 'user_id' => 3, 'role' => 'Designer', 'status' => 'accepted', 'created_at' => $now, 'accepted_at' => $now],
        ]);
    }

    #[Test]
    public function team_builds_show_beside_own_builds_with_the_members_role(): void
    {
        $this->joinTeams();

        // Guest, member, owner and admin: own and accepted team builds, newest finished first;
        // never a pending invite or a draft, whoever's it is.
        foreach ([null, 4, 3, 1] as $as) {
            $builds = $this->builds($as);
            $this->assertSame(['Newest finished', 'Someone else', 'Team rig', 'Older finished'], array_column($builds, 'title'), 'as '.($as ?? 'guest'));
            // null: their own; '': on the team without a role; otherwise the role.
            $this->assertSame([null, '', 'Backend developer', null], array_column($builds, 'role'));
        }
    }

    #[Test]
    public function a_team_build_can_be_pinned_but_an_invite_or_a_draft_cannot(): void
    {
        $this->joinTeams();

        $this->assertSame(200, $this->pin([6, 7, 8])->getStatusCode());
        $this->assertSame([6], $this->storedPins());
        $this->assertSame(['Team rig', true, 'Backend developer'], [$this->builds(null)[0]['title'], $this->builds(null)[0]['pinned'], $this->builds(null)[0]['role']]);
    }

    #[Test]
    public function team_builds_cost_the_same_queries_with_one_or_twenty(): void
    {
        $count = function (?int $as): int {
            $this->builds($as);
            $db = $this->app()->getContainer()->make(ConnectionInterface::class);
            $db->flushQueryLog();
            $db->enableQueryLog();
            $this->builds($as);
            $queries = count($db->getQueryLog());
            $db->disableQueryLog();

            return $queries;
        };

        $now = Carbon::now();
        $join = function (int $from, int $to) use ($now) {
            for ($i = $from; $i < $to; $i++) {
                $this->database()->table('garage_builds')->insert($this->build($i, "Team $i", finished: '2026-11-0'.(1 + $i % 9), owner: 4));
                $this->database()->table('garage_members')->insert(['build_id' => $i, 'user_id' => 3, 'role' => "Role $i", 'status' => 'accepted', 'created_at' => $now, 'accepted_at' => $now]);
            }
        };

        $join(100, 101);
        $one = array_map($count, [null, 4, 3]);
        $join(101, 120);

        $this->assertSame($one, array_map($count, [null, 4, 3]));
        $this->assertCount(6, $this->builds(null));
        $this->assertCount(22, $this->dossier(3)['buildChoices']);
    }
}
