<?php

namespace Ernestdefoe\Dossier\Garage;

use Ernestdefoe\Dossier\FinishedBuilds;
use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Category;
use ErnestDefoe\Garage\Settings;
use ErnestDefoe\Garage\Storage\Photos;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The member's finished builds, as Garage shows them to this viewer: the ones
 * they own and the ones they're on the team of (Garage's featuring()), the
 * pinned ones first in their order, then newest finished first. Published
 * only, never a draft, not even to the owner or a moderator, so a pin on a
 * build that was since unpublished, deleted or reopened simply drops out.
 *
 * Three queries however many builds: the builds, their categories, and the
 * member's own team row on each (for their role). Photos and specs are columns.
 */
class GarageBuilds extends FinishedBuilds
{
    public function __construct(protected Photos $photos)
    {
    }

    public function available(): bool
    {
        return true;
    }

    public function forMember(User $owner, User $actor, array $pins, int $limit): ?array
    {
        if (! $actor->hasPermission(Settings::VIEW) && ! $actor->hasPermission(Settings::MODERATE)) {
            return null;
        }

        $query = $this->finished($owner, $actor)->with([
            'category',
            // Only the member's own row: roleOf() reads it without a query per build.
            'team' => fn ($q) => $q->where('garage_members.user_id', $owner->id),
        ]);

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
            ->limit($limit)
            ->get(['garage_builds.id', 'garage_builds.user_id', 'garage_builds.category_id', 'title', 'slug', 'specs', 'photos', 'finished_at'])
            ->map(fn (Build $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'slug' => $b->slug,
                'cover' => $this->photos->present($b->photos[0] ?? null)['small'] ?? null,
                // A day, not a moment: a timestamp would read as the day before west of UTC.
                'finishedAt' => $b->finished_at?->toDateString(),
                'specs' => $this->specs($b->category, $b->specs ?? []),
                // null: their own build; '' on a team with no role; otherwise the role.
                'role' => $b->user_id === $owner->id ? null : ($b->roleOf($owner) ?? ''),
                'pinned' => in_array($b->id, $pins, true),
            ])
            ->all();
    }

    public function pinnable(User $owner, User $actor, array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $found = $this->finished($owner, $actor)->whereIn('garage_builds.id', $ids)->pluck('garage_builds.id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_intersect($ids, $found));
    }

    /** @return Builder<Build> */
    private function finished(User $owner, User $actor): Builder
    {
        return Build::query()
            ->whereVisibleTo($actor)
            ->featuring($owner)
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
    private function specs(?Category $category, array $specs): array
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
}
