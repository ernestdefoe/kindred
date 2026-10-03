<?php

use Ernestdefoe\Kindred\Api\SimilarController;
use Ernestdefoe\Kindred\ForgetOnRename;
use Flarum\Discussion\Event\Renamed;
use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->get('/kindred/{id}', 'ernestdefoe-kindred.similar', SimilarController::class),

    // A renamed discussion matches different things; its cached list goes.
    (new Extend\Event())
        ->listen(Renamed::class, ForgetOnRename::class),

    (new Extend\Settings())
        ->default('ernestdefoe-kindred.limit', 5)
        ->default('ernestdefoe-kindred.max_age_days', 0)
        ->default('ernestdefoe-kindred.extra_stopwords', '')
        ->default('ernestdefoe-kindred.sidebar', false)
        ->serializeToForum('kindredSidebar', 'ernestdefoe-kindred.sidebar', 'boolval'),
];
