<?php

namespace Ernestdefoe\Dossier;

use Flarum\User\User;

/**
 * What a member earned elsewhere: certificates and the skills they came with.
 * Nothing on its own; with Syllabus enabled, extend.php binds this to
 * Syllabus\SyllabusEarned, which reads Syllabus's public achievements API.
 */
class Earned
{
    /**
     * Shaped like Syllabus's Achievements::forMember(), or null when there is
     * nothing to show this viewer.
     *
     * @return array{certificates: list<array<string, mixed>>, skills: list<array<string, mixed>>}|null
     */
    public function forMember(User $member, User $actor): ?array
    {
        return null;
    }
}
