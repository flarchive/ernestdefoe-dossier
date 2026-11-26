<?php

namespace Ernestdefoe\Dossier\Projects;

use Ernestdefoe\Dossier\PublishedProjects;
use Ernestdefoe\Dossier\Url;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The member's published projects (their own and those they co-author), as
 * Projects shows them to this viewer, newest and featured first. One query.
 */
class ProjectsList extends PublishedProjects
{
    // By name: Projects isn't a dev dependency, so static analysis can't load its model.
    private const PROJECT = 'ErnestDefoe\\Projects\\Model\\Project';

    public function forMember(User $owner, User $actor): ?array
    {
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
}
