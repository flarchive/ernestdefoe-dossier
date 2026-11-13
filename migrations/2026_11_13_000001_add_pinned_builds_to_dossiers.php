<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * The Garage builds the member pinned to the top of Finished builds, as a JSON
 * list of build ids in their order. Checked against Garage on every read and save.
 */
return [
    'up' => function (Builder $schema) {
        $schema->table('dossiers', function (Blueprint $table) {
            $table->text('pinned_builds')->nullable();
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('dossiers', function (Blueprint $table) {
            $table->dropColumn('pinned_builds');
        });
    },
];
