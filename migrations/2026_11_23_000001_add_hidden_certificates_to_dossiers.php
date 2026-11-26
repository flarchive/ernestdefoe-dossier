<?php

use Flarum\Database\Migration;

/*
 * The Syllabus certificates the member chose not to show on their dossier, as
 * a JSON list of verification codes. Syllabus keeps the certificates themselves.
 */
return Migration::addColumns('dossiers', [
    'hidden_certificates' => ['text', 'nullable' => true],
]);
