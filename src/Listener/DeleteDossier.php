<?php

namespace Ernestdefoe\Dossier\Listener;

use Ernestdefoe\Dossier\Dossier;
use Ernestdefoe\Dossier\Entry;
use Ernestdefoe\Dossier\Skill;
use Ernestdefoe\Dossier\Storage\Images;
use Flarum\User\Event\Deleting;

/**
 * A deleted member's dossier goes with them: their certificate pictures, their
 * entries and dossier row (here as well as by foreign key, which SQLite only
 * enforces when it is switched on), and the typed skills nobody else uses.
 */
class DeleteDossier
{
    public function __construct(protected Images $images)
    {
    }

    public function handle(Deleting $event): void
    {
        $id = $event->user->id;

        foreach (Entry::query()->where('user_id', $id)->whereNotNull('image')->pluck('image') as $image) {
            $this->images->delete($image);
        }

        $skills = Entry::query()->where('user_id', $id)->whereNotNull('skill_id')->pluck('skill_id')->all();
        Entry::query()->where('user_id', $id)->delete();
        Dossier::query()->where('user_id', $id)->delete();

        foreach ($skills as $skill) {
            Skill::pruneUnused($skill);
        }
    }
}
