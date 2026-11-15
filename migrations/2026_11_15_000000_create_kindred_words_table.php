<?php

use Ernestdefoe\Kindred\WordIndex;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('kindred_words')) {
            $schema->create('kindred_words', function (Blueprint $table) {
                $table->string('word', 64);
                $table->unsignedInteger('discussion_id');

                // Index names are Laravel's own, so they carry the table prefix.
                $table->primary(['word', 'discussion_id']);
                $table->index('discussion_id');
                $table->foreign('discussion_id')->references('id')->on('discussions')->cascadeOnDelete();
            });
        }

        // The discussions already there.
        resolve(WordIndex::class)->fill();
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('kindred_words');
    },
];
