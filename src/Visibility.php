<?php

namespace Ernestdefoe\Dossier;

use Flarum\User\User;

/**
 * Who may see what. Each section (and each link) is shown to everyone, to
 * signed-in members, or only to its owner. "Only me" also lets in whoever may
 * edit any dossier, since they have to see what they are editing.
 */
final class Visibility
{
    public const EVERYONE = 'everyone';

    public const MEMBERS = 'members';

    public const PRIVATE = 'private';

    public const LEVELS = [self::EVERYONE, self::MEMBERS, self::PRIVATE];

    /** The sections whose visibility can be chosen, in the order they are shown. */
    public const SECTIONS = ['about', 'timeline', 'skills', 'languages', 'projects', 'series', 'builds', 'certificates', 'links', 'open_to'];

    /** A level as a number: the wider, the higher. users.dossier_reach keeps the widest one in use. */
    public const RANK = [self::PRIVATE => 1, self::MEMBERS => 2, self::EVERYONE => 3];

    public static function normalize(mixed $level): string
    {
        return in_array($level, self::LEVELS, true) ? $level : self::EVERYONE;
    }

    /** The narrower of two levels: a members-only link in a section for everyone is still members-only. */
    public static function narrower(string $a, string $b): string
    {
        return self::RANK[self::normalize($a)] <= self::RANK[self::normalize($b)] ? self::normalize($a) : self::normalize($b);
    }

    public static function seesPrivate(User $actor, User $owner): bool
    {
        return ! $actor->isGuest() && ($actor->id === $owner->id || $actor->hasPermission('ernestdefoe-dossier.editAny'));
    }

    public static function canView(User $actor, User $owner): bool
    {
        return self::seesPrivate($actor, $owner) || $actor->hasPermission('ernestdefoe-dossier.view');
    }

    public static function canEdit(User $actor, User $owner): bool
    {
        if ($actor->isGuest()) {
            return false;
        }

        return $actor->id === $owner->id
            ? $actor->hasPermission('ernestdefoe-dossier.have')
            : $actor->hasPermission('ernestdefoe-dossier.editAny');
    }

    /** The narrowest rank this actor can see on this owner's dossier. */
    public static function rankFor(User $actor, User $owner): int
    {
        if (self::seesPrivate($actor, $owner)) {
            return self::RANK[self::PRIVATE];
        }

        return $actor->isGuest() ? self::RANK[self::EVERYONE] : self::RANK[self::MEMBERS];
    }

    public static function allows(string $level, User $actor, User $owner): bool
    {
        return self::RANK[self::normalize($level)] >= self::rankFor($actor, $owner);
    }
}
