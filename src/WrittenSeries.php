<?php

namespace Ernestdefoe\Dossier;

use Flarum\User\User;

/**
 * The series a writer published. Nothing on its own; with Anthology enabled,
 * extend.php binds this to Anthology\AnthologySeries.
 */
class WrittenSeries
{
    /** @return list<array<string, mixed>>|null */
    public function forMember(User $owner, User $actor): ?array
    {
        return null;
    }
}
