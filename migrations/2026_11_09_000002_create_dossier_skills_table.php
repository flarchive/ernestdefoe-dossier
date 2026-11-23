<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('dossier_skills', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 60);
            // The name lowercased: one row per skill however members type it.
            $table->string('slug', 60)->unique();
            $table->unsignedInteger('category_id')->nullable();
            // false = typed by a member and waiting for an admin to add it to the catalogue.
            $table->boolean('approved')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->dateTime('created_at')->nullable();

            $table->index(['approved', 'category_id']);
            $table->foreign('category_id')->references('id')->on('dossier_skill_categories')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('dossier_skills');
    },
];
