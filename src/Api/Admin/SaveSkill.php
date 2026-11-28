<?php

namespace Ernestdefoe\Dossier\Api\Admin;

use Carbon\Carbon;
use Ernestdefoe\Dossier\Entry;
use Ernestdefoe\Dossier\Input;
use Ernestdefoe\Dossier\Reach;
use Ernestdefoe\Dossier\Skill;
use Ernestdefoe\Dossier\SkillCategory;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Catalogue skills (admins): POST adds one, PATCH renames, recategorizes or
 * approves one (a member's typed skill joins the catalogue), DELETE removes
 * one from the catalogue and from every dossier that lists it.
 */
class SaveSkill implements RequestHandlerInterface
{
    public function __construct(protected Input $input)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();
        $id = (int) Arr::get($request->getAttribute('routeParameters', []), 'id');
        $body = (array) $request->getParsedBody();

        if ($request->getMethod() === 'DELETE') {
            $skill = Skill::query()->findOrFail($id);
            $owners = Entry::query()->where('skill_id', $skill->id)->distinct()->pluck('user_id')->all();
            Entry::query()->where('skill_id', $skill->id)->delete();
            $skill->delete();

            // Someone whose only filled-in section was this skill has nothing left to show.
            foreach (User::query()->whereIn('id', $owners)->get() as $owner) {
                Reach::refresh($owner);
            }

            return new EmptyResponse();
        }

        $skill = $id ? Skill::query()->findOrFail($id) : new Skill();

        if (array_key_exists('name', $body) || ! $id) {
            $name = $this->input->line($body['name'] ?? null, 60, 'name') ?? $this->input->fail('name', 'required');
            $clash = Skill::query()->where('slug', Skill::slugFor($name))->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();

            if ($clash) {
                $this->input->fail('name', 'skill_exists');
            }

            $skill->name = $name;
            $skill->slug = Skill::slugFor($name);
        }

        if (array_key_exists('categoryId', $body)) {
            $skill->category_id = $body['categoryId'] ? SkillCategory::query()->findOrFail((int) $body['categoryId'])->id : null;
        }

        if (! $id) {
            $skill->approved = true;
            $skill->created_by = $actor->id;
            $skill->created_at = Carbon::now();
        } elseif (! empty($body['approved'])) {
            $skill->approved = true;
        }

        $skill->save();

        return new JsonResponse(['data' => ['id' => $skill->id, 'name' => $skill->name, 'categoryId' => $skill->category_id, 'approved' => $skill->approved]], $id ? 200 : 201);
    }
}
