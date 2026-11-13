<?php

namespace Ernestdefoe\Dossier\Api;

use Ernestdefoe\Dossier\Entry;
use Ernestdefoe\Dossier\Presenter;
use Ernestdefoe\Dossier\Reach;
use Ernestdefoe\Dossier\Skill;
use Ernestdefoe\Dossier\Storage\Images;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** DELETE /api/dossiers/{id}/entries/{entry}. */
class DeleteEntry extends Controller
{
    public function __construct(Presenter $presenter, protected Images $images)
    {
        parent::__construct($presenter);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        [$actor, $owner] = $this->editable($request);

        /** @var Entry $entry */
        $entry = Entry::query()->where('user_id', $owner->id)->findOrFail($this->param($request, 'entry'));
        $this->images->delete($entry->image);
        $entry->delete();
        Skill::pruneUnused($entry->skill_id);
        Reach::refresh($owner);

        return $this->respond($owner, $actor);
    }
}
