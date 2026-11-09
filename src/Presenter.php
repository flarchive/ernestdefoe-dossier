<?php

namespace Ernestdefoe\Dossier;

use Ernestdefoe\Dossier\Storage\Images;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
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
 * entries, the skills they name (with their categories, one join), and the
 * member's projects when Projects is enabled.
 */
class Presenter
{
    private const PROJECT = 'ErnestDefoe\\Projects\\Model\\Project';

    public function __construct(
        protected Settings $settings,
        protected Images $images,
        protected ExtensionManager $extensions,
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
        ];

        if ($canEdit) {
            $out['visibility'] = $dossier->visibilities();
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
}
