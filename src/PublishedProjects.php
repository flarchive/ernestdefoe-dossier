<?php

namespace Ernestdefoe\Dossier;

use Flarum\User\User;

/**
 * A member's published projects. Nothing on its own; with Projects enabled,
 * extend.php binds this to Projects\ProjectsList.
 */
class PublishedProjects
{
    /** @return list<array<string, mixed>>|null */
    public function forMember(User $owner, User $actor): ?array
    {
        return null;
    }
}
