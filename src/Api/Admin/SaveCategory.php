<?php

namespace Ernestdefoe\Dossier\Api\Admin;

use Ernestdefoe\Dossier\Input;
use Ernestdefoe\Dossier\Skill;
use Ernestdefoe\Dossier\SkillCategory;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Skill categories (admins): POST adds one, PATCH renames one, DELETE removes
 * one (its skills stay, without a category), POST /order sorts them.
 */
class SaveCategory implements RequestHandlerInterface
{
    public function __construct(protected Input $input)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();
        $id = (int) Arr::get($request->getAttribute('routeParameters', []), 'id');
        $body = (array) $request->getParsedBody();
        $path = $request->getUri()->getPath();

        if (str_ends_with($path, '/order')) {
            foreach (array_values(array_map('intval', (array) ($body['ids'] ?? []))) as $position => $categoryId) {
                SkillCategory::query()->where('id', $categoryId)->update(['position' => $position]);
            }

            return new EmptyResponse();
        }

        if ($request->getMethod() === 'DELETE') {
            $category = SkillCategory::query()->findOrFail($id);
            // Its skills stay, without a category (not left to the foreign key, which SQLite may not enforce).
            Skill::query()->where('category_id', $category->id)->update(['category_id' => null]);
            $category->delete();

            return new EmptyResponse();
        }

        $category = $id ? SkillCategory::query()->findOrFail($id) : new SkillCategory();
        $category->name = $this->input->line($body['name'] ?? null, 60, 'name') ?? $this->input->fail('name', 'required');

        if (! $category->exists) {
            $category->position = (int) SkillCategory::query()->max('position') + 1;
        }

        $category->save();

        return new JsonResponse(['data' => ['id' => $category->id, 'name' => $category->name]], $id ? 200 : 201);
    }
}
