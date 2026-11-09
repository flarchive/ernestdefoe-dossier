<?php

namespace Ernestdefoe\Dossier\Api;

use Ernestdefoe\Dossier\Settings;
use Ernestdefoe\Dossier\Skill;
use Ernestdefoe\Dossier\SkillCategory;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/dossier-catalogue: what the dossier editor offers. The skill
 * catalogue with its categories (plus the actor's own typed skills), the
 * open-to chips and whether members may type their own skills.
 *
 * With ?manage=1 (admins only): every skill with how many dossiers use it,
 * the typed ones included, for the admin page.
 */
class Catalogue implements RequestHandlerInterface
{
    public function __construct(protected Settings $settings)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $manage = ! empty($request->getQueryParams()['manage']);

        if ($manage) {
            $actor->assertAdmin();
        } elseif (! $actor->hasPermission('ernestdefoe-dossier.have') && ! $actor->hasPermission('ernestdefoe-dossier.editAny')) {
            throw new PermissionDeniedException();
        }

        $skills = Skill::query()
            ->when(! $manage, fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('approved', true)->orWhere('created_by', $actor->id)))
            ->when($manage, fn (Builder $q) => $q->withCount('entries'))
            ->orderBy('name')
            ->get()
            ->map(fn (Skill $s) => array_filter([
                'id' => $s->id,
                'name' => $s->name,
                'categoryId' => $s->category_id,
                'approved' => $s->approved,
                'uses' => $manage ? (int) $s->getAttribute('entries_count') : null,
            ], fn ($v) => $v !== null))
            ->all();

        return new JsonResponse(['data' => [
            'categories' => SkillCategory::query()->orderBy('position')->orderBy('id')->get(['id', 'name'])->toArray(),
            'skills' => $skills,
            'openTo' => $this->settings->openTo(),
            'customSkills' => $this->settings->customSkills(),
        ]]);
    }
}
