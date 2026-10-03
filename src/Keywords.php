<?php

namespace Ernestdefoe\Kindred;

/**
 * The words in a title that say what it is about.
 */
class Keywords
{
    public function __construct(protected Stopwords $stopwords)
    {
    }

    /**
     * Lowercased, punctuation gone, no stopwords, nothing under three
     * characters, each word once, in the order the title has them.
     *
     * @return list<string>
     */
    public function of(string $title): array
    {
        $words = [];

        foreach ($this->tokens($title) as $word) {
            if (mb_strlen($word) < 3 || is_numeric($word) || $this->stopwords->has($word)) {
                continue;
            }

            $words[$word] = true;
        }

        return array_keys($words);
    }

    /**
     * Every word of a title, lowercased, without punctuation.
     *
     * @return list<string>
     */
    public function tokens(string $title): array
    {
        $title = mb_strtolower($title);
        // An apostrophe inside a word joins it ("don't" is "dont"), anything
        // else that isn't a letter or digit splits.
        $title = preg_replace("/(\p{L})['’](\p{L})/u", '$1$2', $title);

        return preg_split('/[^\p{L}\p{N}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * A word with its plural or singular beside it, so "recipe" finds
     * "recipes" and back again. Deliberately naive: English plurals, nothing
     * clever, nothing that turns one word into a different one.
     *
     * @return list<string>
     */
    public function variants(string $word): array
    {
        $forms = [$word];

        if (mb_strlen($word) > 4 && str_ends_with($word, 'ies')) {
            $forms[] = mb_substr($word, 0, -3).'y';
        } elseif (mb_strlen($word) > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            $forms[] = mb_substr($word, 0, -1);
        } elseif (str_ends_with($word, 'y') && mb_strlen($word) > 3 && ! preg_match('/[aeiou]y$/u', $word)) {
            $forms[] = mb_substr($word, 0, -1).'ies';
        } else {
            $forms[] = $word.'s';
        }

        return $forms;
    }

    /** The one form a word and its plural share, for comparing titles. */
    public function stem(string $word): string
    {
        if (mb_strlen($word) > 4 && str_ends_with($word, 'ies')) {
            return mb_substr($word, 0, -3).'y';
        }

        if (mb_strlen($word) > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return mb_substr($word, 0, -1);
        }

        return $word;
    }
}
