<?php

namespace Ernestdefoe\Kindred;

use Flarum\Discussion\Event\Renamed;

/** A renamed discussion matches different things: new words, and its cached list goes. */
class OnRename
{
    public function __construct(
        protected WordIndex $index,
        protected Matcher $matcher,
    ) {
    }

    public function handle(Renamed $event): void
    {
        $discussion = $event->discussion;

        // Words first, so the next lookup already matches on the new title.
        $this->index->index((int) $discussion->id, (string) $discussion->title);
        $this->matcher->forget((int) $discussion->id);
    }
}
