<?php

namespace Ernestdefoe\Dossier\Api;

use Carbon\Carbon;
use Ernestdefoe\Dossier\Dossier;
use Ernestdefoe\Dossier\Input;
use Ernestdefoe\Dossier\Presenter;
use Ernestdefoe\Dossier\Reach;
use Ernestdefoe\Dossier\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** PATCH /api/dossiers/{id}: About, open-to chips and section visibility. */
class UpdateDossier extends Controller
{
    public function __construct(Presenter $presenter, protected Input $input, protected Settings $settings)
    {
        parent::__construct($presenter);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        [$actor, $owner] = $this->editable($request);
        $changes = $this->input->about($this->body($request), array_column($this->settings->openTo(), 'id'));

        $dossier = Dossier::query()->find($owner->id) ?? new Dossier();
        $dossier->user_id = $owner->id;

        if (isset($changes['visibility'])) {
            $changes['visibility'] = $changes['visibility'] + ($dossier->visibility ?? []);
        }

        foreach ($changes as $key => $value) {
            $dossier->setAttribute($key, $value);
        }

        $dossier->updated_at = Carbon::now();
        $dossier->save();
        Reach::refresh($owner);

        return $this->respond($owner, $actor);
    }
}
