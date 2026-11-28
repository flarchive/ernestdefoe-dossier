<?php

namespace Ernestdefoe\Dossier;

use Flarum\User\User;

/**
 * A member's finished Garage builds. Nothing on its own; with Garage enabled,
 * extend.php binds this to Garage\GarageBuilds.
 */
class FinishedBuilds
{
    public function available(): bool
    {
        return false;
    }

    /**
     * The builds this viewer may see, pinned ones first, at most $limit; null
     * when there is nothing to show this viewer.
     *
     * @param list<int> $pins
     * @return list<array<string, mixed>>|null
     */
    public function forMember(User $owner, User $actor, array $pins, int $limit): ?array
    {
        return null;
    }

    /**
     * Which of these build ids the member may pin, in the order sent.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    public function pinnable(User $owner, User $actor, array $ids): array
    {
        return [];
    }
}
