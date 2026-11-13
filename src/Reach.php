<?php

namespace Ernestdefoe\Dossier;

use Flarum\User\User;

/**
 * Keeps users.dossier_reach up to date: the widest visibility of anything
 * filled in, so a profile can tell whether a viewer would find anything on
 * the dossier without reading it.
 */
class Reach
{
    public static function refresh(User $owner): int
    {
        $dossier = Dossier::query()->find($owner->id);
        $visibility = $dossier ? $dossier->visibilities() : array_fill_keys(Visibility::SECTIONS, Visibility::EVERYONE);
        $levels = [];

        if ($dossier && ($dossier->headline || $dossier->role || $dossier->bio || $dossier->specializations || $dossier->interests)) {
            $levels[] = $visibility['about'];
        }

        if ($dossier && $dossier->open_to) {
            $levels[] = $visibility['open_to'];
        }

        foreach (Entry::query()->where('user_id', $owner->id)->get(['section', 'data']) as $entry) {
            $levels[] = $entry->section === 'links'
                ? Visibility::narrower($visibility['links'], Visibility::normalize($entry->data['visibility'] ?? null))
                : $visibility[$entry->section];
        }

        $reach = $levels ? max(array_map(fn ($level) => Visibility::RANK[$level], $levels)) : 0;

        if ((int) $owner->getAttribute('dossier_reach') !== $reach) {
            User::query()->where('id', $owner->id)->update(['dossier_reach' => $reach]);
            $owner->setRawAttributes(['dossier_reach' => $reach] + $owner->getAttributes(), true);
        }

        return $reach;
    }
}
