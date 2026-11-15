<?php

namespace Ernestdefoe\Kindred;

use Flarum\Discussion\Discussion;
use Illuminate\Database\ConnectionInterface;

/**
 * Every discussion's title words, one row each, so that "which titles share
 * these words" is an index lookup on any database instead of a scan of every
 * title. MySQL and MariaDB match on core's FULLTEXT index first; this is how
 * PostgreSQL and SQLite match, and how MySQL matches when FULLTEXT can't.
 */
class WordIndex
{
    public const TABLE = 'kindred_words';

    /** The column's width; a longer word is kept to its first this many letters. */
    public const WIDTH = 64;

    public function __construct(
        protected ConnectionInterface $db,
        protected Keywords $keywords,
    ) {
    }

    /** The form a word is stored and looked up by. */
    public function key(string $word): string
    {
        return mb_substr($this->keywords->stem($word), 0, self::WIDTH);
    }

    /**
     * What a title is stored as. The forum's own extra ignored words are kept,
     * so changing them needs no rebuild: they are left out of the lookup.
     *
     * @return list<string>
     */
    public function keysOf(string $title): array
    {
        $keys = [];

        foreach ($this->keywords->tokens($title) as $word) {
            if (mb_strlen($word) >= 3 && ! is_numeric($word) && ! in_array($word, Stopwords::SHIPPED, true)) {
                $keys[$this->key($word)] = true;
            }
        }

        return array_map('strval', array_keys($keys));
    }

    public function index(int $discussionId, string $title): void
    {
        $this->db->transaction(function () use ($discussionId, $title) {
            $this->db->table(self::TABLE)->where('discussion_id', $discussionId)->delete();
            $this->insert([$discussionId => $title]);
        });
    }

    public function has(int $discussionId): bool
    {
        return $this->db->table(self::TABLE)->where('discussion_id', $discussionId)->exists();
    }

    /**
     * Index the discussions that have no words yet (all of them, with $all),
     * a chunk at a time. Returns how many were read.
     */
    public function fill(bool $all = false, int $chunk = 500): int
    {
        if ($all) {
            $this->db->table(self::TABLE)->delete();
        }

        $count = 0;

        $this->db->table('discussions')
            ->select('id', 'title')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from(self::TABLE)->whereColumn(self::TABLE.'.discussion_id', 'discussions.id'))
            ->chunkById($chunk, function ($rows) use (&$count) {
                $this->insert($rows->pluck('title', 'id')->all());
                $count += $rows->count();
            });

        return $count;
    }

    /** @param array<int, string|null> $titles */
    protected function insert(array $titles): void
    {
        $rows = [];

        foreach ($titles as $id => $title) {
            foreach ($this->keysOf((string) $title) as $key) {
                $rows[] = ['word' => $key, 'discussion_id' => (int) $id];
            }
        }

        // Ignore, not insert: MySQL's collations make "café" and "cafe" one key.
        foreach (array_chunk($rows, 500) as $batch) {
            $this->db->table(self::TABLE)->insertOrIgnore($batch);
        }
    }

    /** Keeps a discussion's words in step with its title. */
    public function saved(Discussion $discussion): void
    {
        // Created just now, or renamed. A save that only moved its counters
        // (every reply does) leaves the words alone.
        if (($discussion->wasRecentlyCreated && ! $discussion->getChanges()) || $discussion->wasChanged('title')) {
            $this->index((int) $discussion->id, (string) $discussion->title);
        }
    }
}
