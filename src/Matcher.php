<?php

namespace Ernestdefoe\Kindred;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;

/**
 * Which discussions are about the same thing as this one, best first.
 *
 * The answer is the same for everyone, so it is worked out once and cached for
 * six hours. What a visitor may SEE of it is not: the controller narrows the
 * cached list to the reader's own permissions on every request.
 */
class Matcher
{
    /** Bumped when scoring changes, so cached answers from before are redone. */
    public const VERSION = 2;

    /** How long a discussion's list is kept. */
    public const TTL = 6 * 3600;

    /** Candidates kept per discussion — more than any forum shows, so that
     * those a reader can't see still leave enough. */
    public const KEEP = 30;

    /** Below this much of the title in common, it isn't similar. */
    public const MIN_TEXT = 0.25;

    /** Below this overall, it isn't worth a row. */
    public const MIN_SCORE = 0.4;

    public const TAG_BOOST = 0.15;
    public const MAX_TAG_BOOST = 0.3;
    public const RECENCY_BOOST = 0.1;
    /** Days for the recency boost to fall to about a third. */
    public const RECENCY_DAYS = 180;

    protected ?bool $fulltext = null;

    public function __construct(
        protected ConnectionInterface $db,
        protected Cache $cache,
        protected Keywords $keywords,
        protected Stopwords $stopwords,
        protected ExtensionManager $extensions,
    ) {
    }

    public static function cacheKey(int $discussionId): string
    {
        return 'ernestdefoe-kindred.'.$discussionId;
    }

    /**
     * Candidate ids with their scores, best first. Not narrowed to any reader.
     *
     * @return array<int, float>
     */
    public function candidatesFor(Discussion $discussion): array
    {
        // Changing the ignored words (or how scoring works, in an update)
        // changes every answer, so the cached one carries what it was worked
        // out with.
        $signature = md5(self::VERSION.'|'.$this->stopwords->extraSetting());
        $cached = $this->cache->get(self::cacheKey((int) $discussion->id));

        if (is_array($cached) && ($cached['sig'] ?? null) === $signature && is_array($cached['ids'] ?? null)) {
            return $cached['ids'];
        }

        $ids = $this->compute($discussion);

        $this->cache->put(self::cacheKey((int) $discussion->id), ['sig' => $signature, 'ids' => $ids], self::TTL);

        return $ids;
    }

    public function forget(int $discussionId): void
    {
        $this->cache->forget(self::cacheKey($discussionId));
    }

    /**
     * @return array<int, float>
     */
    protected function compute(Discussion $discussion): array
    {
        $words = $this->keywords->of((string) $discussion->title);

        if (! $words) {
            return [];
        }

        $text = $this->hasFulltextIndex() ? $this->byFulltext($discussion, $words) : null;

        // No index, or one that found nothing to compare against (every word
        // was a stopword to the database): match on the words directly.
        if ($text === null) {
            $text = $this->byLike($discussion, $words);
        }

        if (! $text) {
            return [];
        }

        $ourTags = $this->tagsOf([(int) $discussion->id])[(int) $discussion->id] ?? [];
        $theirTags = $ourTags ? $this->tagsOf(array_keys($text)) : [];
        $lastPosted = $this->db->table('discussions')->whereIn('id', array_keys($text))->pluck('last_posted_at', 'id');
        $now = Carbon::now();

        $scores = [];

        foreach ($text as $id => $relevance) {
            if ($relevance < self::MIN_TEXT) {
                continue;
            }

            $shared = count(array_intersect($ourTags, $theirTags[$id] ?? []));
            $score = $relevance + min(self::MAX_TAG_BOOST, $shared * self::TAG_BOOST);

            if ($at = $lastPosted[$id] ?? null) {
                $days = max(0, Carbon::parse($at)->diffInDays($now));
                $score += self::RECENCY_BOOST * exp(-$days / self::RECENCY_DAYS);
            }

            if ($score >= self::MIN_SCORE) {
                $scores[$id] = round($score, 4);
            }
        }

        arsort($scores);

        return array_slice($scores, 0, self::KEEP, true);
    }

    /**
     * Relevance from the database's own FULLTEXT index, as a share of how well
     * the title matches itself — so 1 is "as similar as it gets" and the rare
     * words count for more than the common ones.
     *
     * @param list<string> $words
     * @return array<int, float>|null null when the index can't judge this title
     */
    protected function byFulltext(Discussion $discussion, array $words): ?array
    {
        $terms = [];
        foreach ($words as $word) {
            array_push($terms, ...$this->keywords->variants($word));
        }
        $against = implode(' ', array_unique($terms));

        $match = 'MATCH('.$this->db->getQueryGrammar()->wrap('title').') AGAINST (?)';

        $self = (float) $this->db->table('discussions')
            ->where('id', $discussion->id)
            ->selectRaw($match.' as relevance', [$against])
            ->value('relevance');

        if ($self <= 0) {
            return null;
        }

        $rows = $this->db->table('discussions')
            ->select('id')
            ->selectRaw($match.' as relevance', [$against])
            ->whereRaw($match, [$against])
            ->where('id', '!=', $discussion->id)
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->orderByDesc('relevance')
            ->limit(self::KEEP * 3)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->id] = min(1.0, (float) $row->relevance / $self);
        }

        return $out;
    }

    /**
     * Without a FULLTEXT index: titles containing any of the four longest
     * words, scored by how much of this title's vocabulary they share.
     *
     * @param list<string> $words
     * @return array<int, float>
     */
    protected function byLike(Discussion $discussion, array $words): array
    {
        $top = $words;
        usort($top, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $top = array_slice($top, 0, 4);

        $rows = $this->db->table('discussions')
            ->select('id', 'title')
            ->where('id', '!=', $discussion->id)
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->where(function ($query) use ($top) {
                foreach ($top as $word) {
                    // The stem, so "recipe" finds "recipes" too.
                    $stem = addcslashes($this->keywords->stem($word), '\\%_');
                    $query->orWhere('title', 'like', '%'.$stem.'%');
                }
            })
            ->orderByDesc('last_posted_at')
            ->limit(300)
            ->get();

        $ours = array_unique(array_map([$this->keywords, 'stem'], $words));
        $out = [];

        foreach ($rows as $row) {
            $theirs = array_unique(array_map([$this->keywords, 'stem'], $this->keywords->of((string) $row->title)));
            $shared = count(array_intersect($ours, $theirs));

            if ($shared) {
                $out[(int) $row->id] = $shared / count($ours);
            }
        }

        return $out;
    }

    /**
     * Tag ids per discussion, when flarum/tags is enabled.
     *
     * @param list<int> $ids
     * @return array<int, list<int>>
     */
    protected function tagsOf(array $ids): array
    {
        if (! $ids || ! $this->extensions->isEnabled('flarum-tags')) {
            return [];
        }

        $out = [];
        foreach ($this->db->table('discussion_tag')->whereIn('discussion_id', $ids)->get(['discussion_id', 'tag_id']) as $row) {
            $out[(int) $row->discussion_id][] = (int) $row->tag_id;
        }

        return $out;
    }

    /**
     * Whether discussions.title carries a FULLTEXT index. Core adds one on
     * MySQL and MariaDB; SQLite and PostgreSQL go the LIKE way.
     */
    public function hasFulltextIndex(): bool
    {
        if ($this->fulltext !== null) {
            return $this->fulltext;
        }

        if (! in_array($this->db->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $this->fulltext = false;
        }

        return $this->fulltext = (bool) $this->cache->remember('ernestdefoe-kindred.fulltext', 86400, function () {
            try {
                // Raw SQL: the table prefix is ours to add.
                $table = $this->db->getTablePrefix().'discussions';
                $rows = $this->db->select('SHOW INDEX FROM `'.str_replace('`', '', $table).'`');

                foreach ($rows as $row) {
                    $row = (array) $row;
                    if (($row['Index_type'] ?? '') === 'FULLTEXT' && ($row['Column_name'] ?? '') === 'title') {
                        return 1;
                    }
                }
            } catch (\Throwable) {
            }

            return 0;
        });
    }
}
