<?php

namespace Ernestdefoe\Dossier\Api;

use Ernestdefoe\Dossier\Visibility;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/dossiers/{id}: the member's dossier as the actor may see it. */
class ShowDossier extends Controller
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $owner = $this->owner($request, $actor);

        if (! Visibility::canView($actor, $owner)) {
            throw new PermissionDeniedException();
        }

        return $this->respond($owner, $actor);
    }
}
