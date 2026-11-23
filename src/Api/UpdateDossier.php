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

/** PATCH /api/dossiers/{id}: About, open-to chips, section visibility, pinned builds and hidden Syllabus certificates. */
class UpdateDossier extends Controller
{
    public function __construct(Presenter $presenter, protected Input $input, protected Settings $settings)
    {
        parent::__construct($presenter);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        [$actor, $owner] = $this->editable($request);
        $body = $this->body($request);
        $changes = $this->input->about($body, array_column($this->settings->openTo(), 'id'));

        $dossier = Dossier::query()->find($owner->id) ?? new Dossier();
        $dossier->user_id = $owner->id;

        if (isset($changes['visibility'])) {
            $changes['visibility'] = $changes['visibility'] + ($dossier->visibility ?? []);
        }

        // Pins sent are checked against Garage; pins kept are checked again on every save, so stale ones drop out.
        // Without Garage they are left as they are, for when it comes back.
        $pins = array_key_exists('pinnedBuilds', $body) ? $this->input->ids($body['pinnedBuilds'], 'pinnedBuilds', $this->settings->pinLimit()) : $dossier->pinned_builds;
        if ($pins !== null && $this->presenter->buildsAvailable()) {
            $changes['pinned_builds'] = $this->presenter->pinnable($owner, $actor, $pins);
        }

        // Hiding a certificate only lists its code here; Syllabus keeps the certificate. One code
        // at a time, merged here: an editor who sees fewer certificates can't un-hide the others.
        if (array_key_exists('certificate', $body)) {
            [$code, $hide] = $this->input->certificateToggle($body['certificate'], 'certificate');
            $codes = array_values(array_diff($dossier->hidden_certificates ?? [], [$code]));
            $changes['hidden_certificates'] = ($hide ? [...$codes, $code] : $codes) ?: null;

            if (count($changes['hidden_certificates'] ?? []) > 100) {
                $this->input->fail('certificate', 'too_many', ['max' => 100]);
            }
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
