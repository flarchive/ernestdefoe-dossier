<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('dossier_skill_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 60);
            $table->unsignedSmallInteger('position')->default(0);
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('dossier_skill_categories');
    },
];
