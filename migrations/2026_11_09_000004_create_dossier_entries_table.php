<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('dossier_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            // timeline | skills | languages | certificates | links
            $table->string('section', 20);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('skill_id')->nullable();
            // The entry's fields as JSON, checked per section before they are stored.
            $table->text('data');
            // A certificate's picture on Dossier's disk (always a re-encoded WebP).
            $table->string('image', 100)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            // A dossier is read whole, by its owner, in section order.
            $table->index(['user_id', 'section', 'position']);
            $table->index('skill_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('skill_id')->references('id')->on('dossier_skills')->cascadeOnDelete();
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('dossier_entries');
    },
];
