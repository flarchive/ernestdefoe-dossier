<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('dossiers', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->string('headline', 150)->nullable();
            $table->string('role', 150)->nullable();
            $table->text('bio')->nullable();
            // JSON lists of short strings.
            $table->text('specializations')->nullable();
            $table->text('interests')->nullable();
            $table->text('open_to')->nullable();
            // JSON: section => everyone | members | private.
            $table->text('visibility')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('dossiers');
    },
];
