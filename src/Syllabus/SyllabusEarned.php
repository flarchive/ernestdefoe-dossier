<?php

namespace Ernestdefoe\Dossier\Syllabus;

use Ernestdefoe\Dossier\Earned;
use Ernestdefoe\Syllabus\Achievements;
use Flarum\User\User;

/**
 * A member's Syllabus certificates and earned skills, as this viewer may see
 * them. Syllabus applies its own privacy (the member's "show my courses"
 * choice, the viewer's course access) and caps the list at 100; three
 * queries however many certificates there are.
 */
class SyllabusEarned extends Earned
{
    public function __construct(protected Achievements $achievements)
    {
    }

    public function forMember(User $member, User $actor): ?array
    {
        return $this->achievements->forMember($member, $actor);
    }
}
