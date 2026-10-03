<?php

namespace Ernestdefoe\Kindred;

use Flarum\Discussion\Event\Renamed;

class ForgetOnRename
{
    public function __construct(protected Matcher $matcher)
    {
    }

    public function handle(Renamed $event): void
    {
        $this->matcher->forget((int) $event->discussion->id);
    }
}
