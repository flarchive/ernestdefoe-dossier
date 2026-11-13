<?php

namespace Ernestdefoe\Dossier\Api;

use Carbon\Carbon;
use Ernestdefoe\Dossier\Entry;
use Ernestdefoe\Dossier\Input;
use Ernestdefoe\Dossier\Presenter;
use Ernestdefoe\Dossier\Reach;
use Ernestdefoe\Dossier\Settings;
use Ernestdefoe\Dossier\Skill;
use Ernestdefoe\Dossier\SkillCategory;
use Flarum\Post\Exception\FloodingException;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/dossiers/{id}/entries adds an entry to a section;
 * PATCH /api/dossiers/{id}/entries/{entry} changes one.
 *
 * The entry is always looked up within the member's own dossier, so an id
 * from someone else's is simply not found.
 */
class SaveEntry extends Controller
{
    public function __construct(Presenter $presenter, protected Input $input, protected Settings $settings, protected Cache $cache)
    {
        parent::__construct($presenter);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        [$actor, $owner] = $this->editable($request);
        $body = $this->body($request);
        $id = $this->param($request, 'entry');

        if ($id) {
            /** @var Entry $entry */
            $entry = Entry::query()->where('user_id', $owner->id)->findOrFail($id);
        } else {
            $section = $body['section'] ?? null;

            if (! in_array($section, Entry::SECTIONS, true)) {
                $this->input->fail('section', 'invalid_section');
            }

            if (Entry::query()->where('user_id', $owner->id)->where('section', $section)->count() >= Entry::LIMITS[$section]) {
                $this->input->fail('section', 'too_many_entries', ['max' => Entry::LIMITS[$section]]);
            }

            $entry = new Entry();
            $entry->user_id = $owner->id;
            $entry->section = $section;
            $entry->position = (int) Entry::query()->where('user_id', $owner->id)->where('section', $section)->max('position') + 1;
        }

        $entry->data = $this->input->entry($entry->section, $body);
        $previousSkill = $entry->skill_id;

        if ($entry->section === 'skills') {
            $entry->skill_id = $this->skill($body, $owner, $actor, $entry);
        }

        $entry->updated_at = Carbon::now();
        $entry->save();

        if ($previousSkill && $previousSkill !== $entry->skill_id) {
            Skill::pruneUnused($previousSkill);
        }

        Reach::refresh($owner);

        return $this->respond($owner, $actor, $id ? 200 : 201);
    }

    /**
     * The catalogue skill picked, or the member's own one typed in. A typed
     * name that is already in the catalogue (in any case) is that skill.
     *
     * @param array<string, mixed> $in
     */
    private function skill(array $in, User $owner, User $actor, Entry $entry): int
    {
        if (! empty($in['skillId'])) {
            $skill = Skill::query()->find((int) $in['skillId']);

            // Someone else's skill waiting for review is not offered to others.
            if (! $skill || (! $skill->approved && ! in_array($skill->created_by, [$owner->id, $actor->id], true) && $skill->id !== $entry->skill_id)) {
                $this->input->fail('skillId', 'invalid_skill');
            }
        } else {
            $name = $this->input->line($in['name'] ?? null, 60, 'name') ?? $this->input->fail(array_key_exists('name', $in) ? 'name' : 'skillId', array_key_exists('name', $in) ? 'required' : 'invalid_skill');
            $skill = Skill::query()->where('slug', Skill::slugFor($name))->first();

            if (! $skill) {
                if (! $this->settings->customSkills() && ! $actor->isAdmin()) {
                    $this->input->fail('name', 'custom_skills_off');
                }

                // Typed skills land in a list every admin reads: a member can add 30 a day.
                $counter = 'dossier.skills.'.$actor->id.'.'.date('Ymd');
                $this->cache->add($counter, 0, 2 * 86400);

                if (! $actor->isAdmin() && $this->cache->increment($counter) > 30) {
                    throw new FloodingException();
                }

                $category = empty($in['categoryId']) ? null : SkillCategory::query()->find((int) $in['categoryId']);

                if (! empty($in['categoryId']) && ! $category) {
                    $this->input->fail('categoryId', 'invalid_category');
                }

                $skill = new Skill();
                $skill->name = $name;
                $skill->slug = Skill::slugFor($name);
                $skill->category_id = $category?->id;
                $skill->approved = $actor->isAdmin();
                $skill->created_by = $actor->id;
                $skill->created_at = Carbon::now();
                $skill->save();
            }
        }

        $taken = Entry::query()
            ->where('user_id', $owner->id)
            ->where('section', 'skills')
            ->where('skill_id', $skill->id)
            ->when($entry->exists, fn ($q) => $q->where('id', '!=', $entry->id))
            ->exists();

        if ($taken) {
            $this->input->fail('skillId', 'duplicate_skill');
        }

        return $skill->id;
    }
}
