<?php

namespace Ernestdefoe\Dossier\Api;

use Ernestdefoe\Dossier\Presenter;
use Ernestdefoe\Dossier\Visibility;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** What every dossier endpoint does first: find the member, and check who is asking. */
abstract class Controller implements RequestHandlerInterface
{
    public function __construct(protected Presenter $presenter)
    {
    }

    protected function param(ServerRequestInterface $request, string $name): int
    {
        return (int) Arr::get($request->getAttribute('routeParameters', []), $name);
    }

    /** The member whose dossier this is, if the actor may see them at all. */
    protected function owner(ServerRequestInterface $request, User $actor): User
    {
        /** @var User */
        return User::query()->whereVisibleTo($actor)->findOrFail($this->param($request, 'id'));
    }

    /**
     * The actor and the member, when the actor may change this dossier: its
     * owner (with "Have a dossier") or someone with "Edit any dossier".
     *
     * @return array{0: User, 1: User}
     */
    protected function editable(ServerRequestInterface $request): array
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $owner = $this->owner($request, $actor);

        if (! Visibility::canEdit($actor, $owner)) {
            throw new PermissionDeniedException();
        }

        return [$actor, $owner];
    }

    /** @return array<string, mixed> */
    protected function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    protected function respond(User $owner, User $actor, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $this->presenter->present($owner, $actor)], $status);
    }
}
