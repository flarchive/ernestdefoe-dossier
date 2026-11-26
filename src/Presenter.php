<?php

namespace Ernestdefoe\Dossier;

use Ernestdefoe\Dossier\Storage\Images;
use Flarum\User\User;
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
 * their certificates and earned skills (three) when Syllabus is, and their
 * finished builds, own and team, pins first (three) when Garage is. Each of
 * those is a class bound only while its extension is enabled (extend.php).
 */
class Presenter
{
    public function __construct(
        protected Settings $settings,
        protected Images $images,
        protected Earned $earned,
        protected FinishedBuilds $builds,
        protected PublishedProjects $projects,
        protected WrittenSeries $series,
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

        // Syllabus certificates and skills (three queries, however many), as Syllabus lets this viewer see them.
        $earned = $sees('certificates') ? $this->earned->forMember($owner, $actor) : null;
        $hidden = $dossier->hidden_certificates ?? [];

        $out = [
            'userId' => (string) $owner->id,
            'canEdit' => $canEdit,
            'about' => $sees('about') ? $this->about($dossier) : null,
            'timeline' => $this->list($section('timeline'), fn (Entry $e) => $this->timeline($e)),
            // A badge names its course, so it shows only to whoever may see the certificates.
            'skills' => $this->skills($section('skills'), $sees('certificates') ? $this->verified($earned, $hidden) : []),
            'languages' => $this->list($section('languages'), fn (Entry $e) => ['id' => $e->id] + $e->data),
            'certificates' => $this->certificates($section('certificates'), $earned, $hidden, $canEdit),
            'links' => $this->links($section('links'), $dossier->visibilityOf('links'), $actor, $owner, $canEdit),
            'openTo' => $sees('open_to')
                ? array_values(array_map(fn ($id) => ['id' => $id, 'label' => $openToNames[$id]], array_filter($dossier->open_to ?? [], fn ($id) => isset($openToNames[$id]))))
                : null,
            'projects' => $sees('projects') ? $this->projects->forMember($owner, $actor) : null,
            'series' => $sees('series') ? $this->series->forMember($owner, $actor) : null,
            'builds' => null,
        ];

        // Whoever may edit the dossier gets up to 100 builds, for the pin window; others six.
        $pins = array_slice($dossier->pinned_builds ?? [], 0, $this->settings->pinLimit());
        $builds = $sees('builds') ? $this->builds->forMember($owner, $actor, $pins, $canEdit ? 100 : Settings::BUILDS_SHOWN) : null;
        $out['builds'] = $builds ? array_slice($builds, 0, Settings::BUILDS_SHOWN) : $builds;

        if ($canEdit) {
            $out['visibility'] = $dossier->visibilities();
            $out['earnedSkills'] = $this->earnedSkills($earned);

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
     * @param array<string, list<string>> $verified course titles by skill slug
     * @return list<array<string, mixed>>|null
     */
    private function skills(?Collection $entries, array $verified): ?array
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
            ->map(function (Entry $e) use ($skills, $verified) {
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
                    // The courses that earned it; null for a skill the member only picked themselves.
                    'verified' => $verified[Skill::slugFor($skill->name)] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The member's own certificates, in their order, then their Syllabus ones,
     * newest first. Syllabus certificates are read-only here; one the member
     * hid is left out, except for whoever edits the dossier, who sees it
     * marked hidden so they can show it again.
     *
     * @param Collection<int, Entry>|null $entries
     * @param array{certificates: list<array<string, mixed>>}|null $earned
     * @param list<string> $hidden
     * @return list<array<string, mixed>>|null
     */
    private function certificates(?Collection $entries, ?array $earned, array $hidden, bool $canEdit): ?array
    {
        if ($entries === null) {
            return null;
        }

        $own = $entries->map(fn (Entry $e) => ['id' => $e->id] + $e->data + [
            'url' => Url::safe($e->data['url'] ?? null),
            'image' => $this->images->url($e->image),
            'thumb' => $this->images->url($e->image, true),
        ])->values()->all();

        $courses = [];
        foreach ($earned['certificates'] ?? [] as $certificate) {
            $isHidden = in_array($certificate['code'], $hidden, true);
            if ($isHidden && ! $canEdit) {
                continue;
            }

            $courses[] = [
                'id' => 'syllabus-'.$certificate['code'],
                'syllabus' => true,
                'code' => $certificate['code'],
                'title' => $certificate['courseTitle'],
                'issuer' => $certificate['issuer'] ?: null,
                'issued' => substr((string) $certificate['issuedAt'], 0, 7),
                'url' => Url::safe($certificate['verifyUrl'] ?? null),
                'image' => null,
                'thumb' => null,
            ] + ($canEdit ? ['hidden' => $isHidden] : []);
        }

        return [...$own, ...$courses];
    }

    /**
     * The skills a shown certificate earned, keyed by skill slug, each with the
     * courses that earned it.
     *
     * @param array{certificates: list<array<string, mixed>>}|null $earned
     * @param list<string> $hidden
     * @return array<string, list<string>>
     */
    private function verified(?array $earned, array $hidden): array
    {
        $verified = [];

        foreach ($earned['certificates'] ?? [] as $certificate) {
            if (in_array($certificate['code'], $hidden, true)) {
                continue;
            }

            foreach ($certificate['skills'] as $skill) {
                $slug = Skill::slugFor((string) $skill);
                if (! in_array($certificate['courseTitle'], $verified[$slug] ?? [], true)) {
                    $verified[$slug][] = $certificate['courseTitle'];
                }
            }
        }

        return $verified;
    }

    /**
     * For the editor's skill window: every skill the member earned, once,
     * with the courses that earned it, hidden certificates included.
     *
     * @param array{skills: list<array<string, mixed>>}|null $earned
     * @return list<array{name: string, courses: list<string>}>
     */
    private function earnedSkills(?array $earned): array
    {
        $skills = [];

        foreach ($earned['skills'] ?? [] as $skill) {
            $slug = Skill::slugFor((string) $skill['name']);
            $skills[$slug] ??= ['name' => (string) $skill['name'], 'courses' => []];
            if (! in_array($skill['courseTitle'], $skills[$slug]['courses'], true)) {
                $skills[$slug]['courses'][] = (string) $skill['courseTitle'];
            }
        }

        return array_values($skills);
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

}
