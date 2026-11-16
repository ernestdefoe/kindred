<?php

use Ernestdefoe\Kindred\Api\SimilarController;
use Ernestdefoe\Kindred\Console\IndexCommand;
use Ernestdefoe\Kindred\OnRename;
use Flarum\Discussion\Event\Renamed;
use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    // One computed, reader-narrowed answer about a discussion, not a model
    // collection, so a plain handler rather than a JSON:API resource (see
    // SimilarController for why).
    (new Extend\Routes('api'))
        ->get('/kindred/{id}', 'ernestdefoe-kindred.similar', SimilarController::class),

    // Each title's words, for matching without a scan of every title: stored
    // when a discussion is created and replaced when it is renamed (which also
    // drops its cached list). Other saves, such as a reply's counters, leave
    // them alone.
    (new Extend\Event())
        ->listen('eloquent.created: Flarum\\Discussion\\Discussion', 'Ernestdefoe\\Kindred\\WordIndex@created')
        ->listen(Renamed::class, OnRename::class),

    (new Extend\Console())
        ->command(IndexCommand::class),

    (new Extend\Settings())
        ->default('ernestdefoe-kindred.limit', 5)
        ->default('ernestdefoe-kindred.max_age_days', 0)
        ->default('ernestdefoe-kindred.extra_stopwords', '')
        ->default('ernestdefoe-kindred.sidebar', false)
        ->serializeToForum('kindredSidebar', 'ernestdefoe-kindred.sidebar', 'boolval'),
];
