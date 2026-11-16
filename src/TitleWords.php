<?php

namespace Ernestdefoe\Kindred;

use Flarum\Discussion\Discussion;

/**
 * Keeps each discussion's title words in step with its title: stored when it
 * is created, replaced (and its cached list dropped) whenever its title
 * changes, however it was changed: the API, an importer, or another
 * extension saving the model directly. Other saves, such as a reply's
 * counters, leave them alone.
 */
class TitleWords
{
    public function __construct(
        protected WordIndex $index,
        protected Matcher $matcher,
    ) {
    }

    public function created(Discussion $discussion): void
    {
        $this->index->index((int) $discussion->id, (string) $discussion->title);
    }

    public function updated(Discussion $discussion): void
    {
        if ($discussion->wasChanged('title')) {
            // Words first, so the next lookup already matches on the new title.
            $this->index->index((int) $discussion->id, (string) $discussion->title);
            $this->matcher->forget((int) $discussion->id);
        }
    }
}
