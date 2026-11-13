<?php

namespace Ernestdefoe\Dossier;

// Anthology and Garage are optional: these names resolve only once seriesAvailable() or buildsAvailable() has found them.
use Ernestdefoe\Anthology\Covers;
use Ernestdefoe\Anthology\Series;
use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Category as BuildCategory;
use ErnestDefoe\Garage\Settings as GarageSettings;
use ErnestDefoe\Garage\Storage\Photos;
use Ernestdefoe\Dossier\Storage\Images;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A dossier as one viewer may see it.
 *
 * 🚨 A section the viewer may not see is left out of the answer entirely
 * (null), never sent and hidden in the browser, and so is a link narrower
 * than the viewer. The section settings themselves only go to whoever can
 * edit the dossier.
 *
 * A fixed number of queries whatever the dossier holds: the dossier row, its
 * entries, the skills they name (with their categories, one join), the
 * member's projects when Projects is enabled, their series when Anthology is,
 * and their finished builds (with their categories, pins first) when Garage is.
 */
class Presenter
{
    private const PROJECT = 'ErnestDefoe\\Projects\\Model\\Project';

    public function __construct(
        protected Settings $settings,
        protected Images $images,
        protected ExtensionManager $extensions,
        protected Container $container,
    ) {
    }

    /** @return array<string, mixed> */
    public function present(User $owner, User $actor): array
    {
        $canEdit = Visibility::canEdit($actor, $owner);
        $dossier = Dossier::query()->find($owner->id) ?? new Dossier();
        $sees = fn (string $section) => Visibility::allows($dossier->visibilityOf($section), $actor, $owner);

        $entries = Entry::query()
            ->where('user_id', $owner->id)
            ->whereIn('section', array_values(array_filter(Entry::SECTIONS, $sees)) ?: ['-'])
            ->orderBy('section')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('section');

        $section = fn (string $name) => $sees($name) ? $entries->get($name, new Collection()) : null;

        $openToNames = array_column($this->settings->openTo(), 'label', 'id');

        $out = [
            'userId' => (string) $owner->id,
            'canEdit' => $canEdit,
            'about' => $sees('about') ? $this->about($dossier) : null,
            'timeline' => $this->list($section('timeline'), fn (Entry $e) => $this->timeline($e)),
            'skills' => $this->skills($section('skills')),
            'languages' => $this->list($section('languages'), fn (Entry $e) => ['id' => $e->id] + $e->data),
            'certificates' => $this->list($section('certificates'), fn (Entry $e) => ['id' => $e->id] + $e->data + [
                'url' => Url::safe($e->data['url'] ?? null),
                'image' => $this->images->url($e->image),
                'thumb' => $this->images->url($e->image, true),
            ]),
            'links' => $this->links($section('links'), $dossier->visibilityOf('links'), $actor, $owner, $canEdit),
            'openTo' => $sees('open_to')
                ? array_values(array_map(fn ($id) => ['id' => $id, 'label' => $openToNames[$id]], array_filter($dossier->open_to ?? [], fn ($id) => isset($openToNames[$id]))))
                : null,
            'projects' => $sees('projects') ? $this->projects($owner, $actor) : null,
            'series' => $sees('series') ? $this->series($owner, $actor) : null,
            'builds' => null,
        ];

        $builds = $sees('builds') ? $this->builds($owner, $actor, $dossier, $canEdit) : null;
        $out['builds'] = $builds ? array_slice($builds, 0, Settings::BUILDS_SHOWN) : $builds;

        if ($canEdit) {
            $out['visibility'] = $dossier->visibilities();

            // What the pin window offers: every finished build, pins first. Same query, no extra one.
            if ($builds !== null) {
                $out['pinLimit'] = $this->settings->pinLimit();
                $out['buildChoices'] = array_map(fn (array $b) => ['id' => $b['id'], 'title' => $b['title'], 'finishedAt' => $b['finishedAt'], 'pinned' => $b['pinned']], $builds);
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    private function about(Dossier $dossier): ?array
    {
        $about = [
            'headline' => $dossier->headline,
            'role' => $dossier->role,
            'bio' => $dossier->bio,
            'specializations' => $dossier->specializations ?? [],
            'interests' => $dossier->interests ?? [],
        ];

        return array_filter($about) ? $about : null;
    }

    /**
     * @param Collection<int, Entry>|null $entries
     * @return list<array<string, mixed>>|null
     */
    private function list(?Collection $entries, callable $map): ?array
    {
        return $entries === null ? null : $entries->map($map)->values()->all();
    }

    /** @return array<string, mixed> */
    private function timeline(Entry $entry): array
    {
        return ['id' => $entry->id] + $entry->data + ['end' => null, 'description' => null];
    }

    /**
     * Skills with their names and categories, from one query.
     *
     * @param Collection<int, Entry>|null $entries
     * @return list<array<string, mixed>>|null
     */
    private function skills(?Collection $entries): ?array
    {
        if ($entries === null) {
            return null;
        }

        $ids = $entries->pluck('skill_id')->filter()->unique()->values()->all();
        $skills = $ids
            ? Skill::query()
                ->leftJoin('dossier_skill_categories', 'dossier_skill_categories.id', '=', 'dossier_skills.category_id')
                ->whereIn('dossier_skills.id', $ids)
                ->get(['dossier_skills.id', 'dossier_skills.name', 'dossier_skills.category_id', 'dossier_skills.approved', 'dossier_skill_categories.name as category_name', 'dossier_skill_categories.position as category_position'])
                ->keyBy('id')
            : new Collection();

        return $entries
            ->filter(fn (Entry $e) => $skills->has($e->skill_id))
            ->map(function (Entry $e) use ($skills) {
                $skill = $skills->get($e->skill_id);

                return [
                    'id' => $e->id,
                    'skillId' => $skill->id,
                    'name' => $skill->name,
                    'categoryId' => $skill->category_id,
                    'category' => $skill->getAttribute('category_name'),
                    'categoryPosition' => $skill->getAttribute('category_position'),
                    'custom' => ! $skill->approved,
                    'level' => (int) ($e->data['level'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, Entry>|null $entries
     * @return list<array<string, mixed>>|null
     */
    private function links(?Collection $entries, string $sectionLevel, User $actor, User $owner, bool $canEdit): ?array
    {
        if ($entries === null) {
            return null;
        }

        $links = [];

        foreach ($entries as $entry) {
            $url = Url::safe($entry->data['url'] ?? null);
            $level = Visibility::normalize($entry->data['visibility'] ?? null);

            if ($url === null || ! Visibility::allows(Visibility::narrower($sectionLevel, $level), $actor, $owner)) {
                continue;
            }

            $kind = (string) ($entry->data['kind'] ?? 'custom');
            [$icon, $site] = Url::icon($kind, $url);
            $link = ['id' => $entry->id, 'kind' => $kind, 'url' => $url, 'label' => $entry->data['label'] ?? null, 'icon' => $icon, 'site' => $site];

            if ($canEdit) {
                $link['visibility'] = $level;
            }

            $links[] = $link;
        }

        return $links;
    }

    /**
     * The member's published projects (their own and those they co-author),
     * as Projects shows them to this viewer. Nothing without Projects.
     *
     * @return list<array<string, mixed>>|null
     */
    private function projects(User $owner, User $actor): ?array
    {
        if (! $this->projectsAvailable()) {
            return null;
        }

        $class = self::PROJECT;

        /** @var Builder<\Illuminate\Database\Eloquent\Model> $query */
        $query = $class::query();

        return $query
            ->whereVisibleTo($actor)
            ->where('status', 'published')
            ->where(function (Builder $q) use ($owner) {
                $q->where('user_id', $owner->id)
                    ->orWhereHas('coAuthors', fn (Builder $c) => $c->where('user_id', $owner->id));
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'title', 'slug', 'excerpt', 'image_path'])
            ->map(fn ($p) => [
                'id' => (int) $p->getAttribute('id'),
                'title' => (string) $p->getAttribute('title'),
                'slug' => (string) $p->getAttribute('slug'),
                'excerpt' => $p->getAttribute('excerpt'),
                'image' => Url::safeOrPath($p->getAttribute('image_path')),
            ])
            ->all();
    }

    public function projectsAvailable(): bool
    {
        return class_exists(self::PROJECT) && $this->extensions->isEnabled('ernestdefoe-projects');
    }

    /**
     * The member's series, as Anthology shows them to this viewer: only series
     * with a chapter they can open, counting only those chapters. One query.
     * Nothing without Anthology, or for a viewer who may not read series.
     *
     * @return list<array<string, mixed>>|null
     */
    private function series(User $owner, User $actor): ?array
    {
        if (! $this->seriesAvailable() || ! $actor->hasPermission('anthology.view')) {
            return null;
        }

        $covers = $this->container->make(Covers::class);
        $visible = fn (Builder $d) => $d->whereVisibleTo($actor);

        return Series::query()
            ->select(['anthology_series.id', 'title', 'slug', 'cover_path', 'status'])
            ->where('user_id', $owner->id)
            ->whereHas('discussions', $visible)
            ->withCount(['discussions as chapter_count' => $visible])
            ->orderByDesc('last_chapter_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Series $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'slug' => $s->slug,
                'cover' => $covers->url($s),
                'complete' => $s->status === 'complete',
                'chapters' => (int) $s->getAttribute('chapter_count'),
            ])
            ->all();
    }

    public function seriesAvailable(): bool
    {
        return class_exists(Series::class) && $this->extensions->isEnabled('ernestdefoe-anthology');
    }

    /**
     * The member's finished builds as Garage shows them to this viewer: the
     * ones they pinned first, in their order, then the rest newest finished
     * first. Published only, never a draft, not even to the owner or a
     * moderator, so a pin on a build that was since unpublished, deleted or
     * reopened simply drops out. Two queries (builds, then their categories)
     * however many there are; photos and specs are columns. Nothing without
     * Garage, or for a viewer who may not see builds.
     *
     * Whoever may edit the dossier gets up to 100, for the pin window; others six.
     *
     * @return list<array<string, mixed>>|null
     */
    private function builds(User $owner, User $actor, Dossier $dossier, bool $canEdit): ?array
    {
        if (! $this->buildsAvailable() || ! ($actor->hasPermission(GarageSettings::VIEW) || $actor->hasPermission(GarageSettings::MODERATE))) {
            return null;
        }

        $photos = $this->container->make(Photos::class);
        $pins = array_slice($dossier->pinned_builds ?? [], 0, $this->settings->pinLimit());
        $query = $this->finishedBuilds($owner, $actor)->with('category');

        if ($pins) {
            // CASE id WHEN pin1 THEN 0 WHEN pin2 THEN 1 … ELSE n END: pins first, in their order.
            $id = $query->getQuery()->getGrammar()->wrap('garage_builds.id');
            $bindings = [];
            foreach ($pins as $position => $pin) {
                array_push($bindings, $pin, $position);
            }
            $query->orderByRaw("CASE $id".str_repeat(' WHEN ? THEN ?', count($pins)).' ELSE ? END', [...$bindings, count($pins)]);
        }

        return $query
            ->orderByDesc('garage_builds.finished_at')
            ->orderByDesc('garage_builds.id')
            ->limit($canEdit ? 100 : Settings::BUILDS_SHOWN)
            ->get(['garage_builds.id', 'garage_builds.category_id', 'title', 'slug', 'specs', 'photos', 'finished_at'])
            ->map(fn (Build $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'slug' => $b->slug,
                'cover' => $photos->present($b->photos[0] ?? null)['small'] ?? null,
                // A day, not a moment: a timestamp would read as the day before west of UTC.
                'finishedAt' => $b->finished_at?->toDateString(),
                'specs' => $this->buildSpecs($b->category, $b->specs ?? []),
                'pinned' => in_array($b->id, $pins, true),
            ])
            ->all();
    }

    /**
     * The pins a member may keep: their own published, finished builds that the
     * actor saving can see in Garage, in the order sent, at most the admin's
     * limit. Anything else is dropped. One query.
     *
     * @param list<int> $ids
     * @return list<int>|null
     */
    public function pinnable(User $owner, User $actor, array $ids): ?array
    {
        $ids = array_slice($ids, 0, $this->settings->pinLimit());

        if (! $ids) {
            return null;
        }

        $found = $this->finishedBuilds($owner, $actor)->whereIn('garage_builds.id', $ids)->pluck('garage_builds.id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_intersect($ids, $found)) ?: null;
    }

    /** @return Builder<Build> */
    private function finishedBuilds(User $owner, User $actor): Builder
    {
        return Build::query()
            ->whereVisibleTo($actor)
            ->where('garage_builds.user_id', $owner->id)
            ->whereNotNull('garage_builds.published_at')
            ->whereNotNull('garage_builds.finished_at');
    }

    /**
     * The spec fields the build's category shows on Garage's cards, as "value unit",
     * at most three, like Garage's own cards.
     *
     * @param array<string, string|float|int> $specs
     * @return list<string>
     */
    private function buildSpecs(?BuildCategory $category, array $specs): array
    {
        $line = [];

        foreach ($category->fields ?? [] as $field) {
            if (empty($field['card']) || ($field['type'] ?? '') === 'link' || ! isset($specs[$field['key']])) {
                continue;
            }

            $value = $specs[$field['key']];
            if (($field['type'] ?? '') === 'number') {
                // Grouped only from five digits: a year (1974) or a model number stays as typed.
                $value = abs((float) $value) >= 10000 ? number_format((float) $value, floor((float) $value) == $value ? 0 : 1) : (string) $value;
                $value = ! empty($field['unit']) ? "$value {$field['unit']}" : $value;
            }

            $line[] = (string) $value;
            if (count($line) === 3) {
                break;
            }
        }

        return $line;
    }

    public function buildsAvailable(): bool
    {
        return class_exists(Build::class) && $this->extensions->isEnabled('ernestdefoe-garage');
    }
}
