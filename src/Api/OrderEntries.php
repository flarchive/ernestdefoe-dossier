<?php

namespace Ernestdefoe\Dossier\Api;

use Ernestdefoe\Dossier\Entry;
use Ernestdefoe\Dossier\Input;
use Ernestdefoe\Dossier\Presenter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/dossiers/{id}/order: {section, ids} puts a section's entries in that order. */
class OrderEntries extends Controller
{
    public function __construct(Presenter $presenter, protected Input $input)
    {
        parent::__construct($presenter);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        [$actor, $owner] = $this->editable($request);
        $body = $this->body($request);
        $section = $body['section'] ?? null;

        if (! in_array($section, Entry::SECTIONS, true) || ! is_array($body['ids'] ?? null)) {
            $this->input->fail('section', 'invalid_section');
        }

        $ids = array_slice(array_values(array_unique(array_map('intval', $body['ids']))), 0, Entry::LIMITS[$section]);
        $mine = Entry::query()->where('user_id', $owner->id)->where('section', $section)->whereIn('id', $ids)->pluck('position', 'id');

        foreach ($ids as $position => $id) {
            if ($mine->has($id) && (int) $mine->get($id) !== $position) {
                Entry::query()->where('id', $id)->update(['position' => $position]);
            }
        }

        return $this->respond($owner, $actor);
    }
}
