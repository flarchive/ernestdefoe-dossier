<?php

namespace Ernestdefoe\Dossier\Anthology;

use Ernestdefoe\Anthology\Covers;
use Ernestdefoe\Anthology\Series;
use Ernestdefoe\Dossier\WrittenSeries;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The member's series, as Anthology shows them to this viewer: only series
 * with a chapter they can open, counting only those chapters. One query.
 * Nothing for a viewer who may not read series.
 */
class AnthologySeries extends WrittenSeries
{
    public function __construct(protected Covers $covers)
    {
    }

    public function forMember(User $owner, User $actor): ?array
    {
        if (! $actor->hasPermission('anthology.view')) {
            return null;
        }

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
                'cover' => $this->covers->url($s),
                'complete' => $s->status === 'complete',
                'chapters' => (int) $s->getAttribute('chapter_count'),
            ])
            ->all();
    }
}
