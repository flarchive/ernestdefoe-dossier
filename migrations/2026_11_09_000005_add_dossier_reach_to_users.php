<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * The widest visibility of anything filled in on the member's dossier
 * (0 nothing, 1 only them, 2 members, 3 everyone), kept up to date on every
 * save. It lets a profile decide whether to open on the dossier, and whether
 * to show its tab, without reading the dossier.
 */
return [
    'up' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('dossier_reach')->default(0);
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn('dossier_reach');
        });
    },
];
