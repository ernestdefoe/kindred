<?php

namespace Ernestdefoe\Kindred;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Words too common to say two titles are about the same thing.
 *
 * The shipped list is English. A forum adds its own — its sport, its town,
 * the word every title on it contains — under "extra words to ignore", which
 * is one list whatever language the visitor reads in.
 */
class Stopwords
{
    public const SHIPPED = [
        'about', 'above', 'after', 'again', 'against', 'all', 'almost', 'also', 'although', 'always', 'among', 'and',
        'another', 'any', 'anybody', 'anyone', 'anything', 'anyway', 'are', 'aren', 'around', 'ask', 'asked', 'asking',
        'away', 'back', 'bad', 'because', 'been', 'before', 'being', 'below', 'best', 'better', 'between', 'big',
        'both', 'but', 'can', 'cannot', 'cant', 'could', 'couldn', 'did', 'didn', 'does', 'doesn', 'doing', 'don',
        'done', 'dont', 'down', 'during', 'each', 'either', 'else', 'enough', 'even', 'ever', 'every', 'few', 'find',
        'first', 'for', 'from', 'further', 'get', 'gets', 'getting', 'give', 'going', 'gonna', 'good', 'got', 'great',
        'had', 'hadn', 'has', 'hasn', 'have', 'haven', 'having', 'hello', 'help', 'her', 'here', 'hers', 'herself',
        'hey', 'him', 'himself', 'his', 'how', 'however', 'ill', 'into', 'isn', 'its', 'itself',
        'just', 'keep', 'know', 'last', 'least', 'less', 'let', 'lets', 'like', 'little', 'long', 'look', 'looking',
        'lot', 'lots', 'made', 'make', 'makes', 'many', 'may', 'maybe', 'mean', 'might', 'mine', 'more', 'most',
        'much', 'must', 'mustn', 'myself', 'need', 'needs', 'never', 'new', 'next', 'nobody', 'non', 'none', 'nor',
        'not', 'nothing', 'now', 'off', 'often', 'okay', 'old', 'once', 'one', 'only', 'onto', 'other', 'others',
        'our', 'ours', 'ourselves', 'out', 'over', 'own', 'please', 'post', 'posted', 'posts', 'pretty', 'put',
        'question', 'questions', 'quite', 'rather', 'really', 'right', 'said', 'same', 'say', 'says', 'see', 'seem',
        'seems', 'shall', 'she', 'should', 'shouldn', 'since', 'some', 'somebody', 'someone', 'something', 'still',
        'such', 'sure', 'take', 'than', 'thank', 'thanks', 'that', 'thats', 'the', 'their', 'theirs', 'them',
        'themselves', 'then', 'there', 'these', 'they', 'thing', 'things', 'think', 'this', 'those', 'though',
        'thoughts', 'through', 'thx', 'today', 'too', 'topic', 'thread', 'try', 'trying', 'two', 'under', 'until',
        'upon', 'use', 'used', 'using', 'very', 'via', 'want', 'wanted', 'was', 'wasn', 'way', 'well', 'went',
        'were', 'weren', 'what', 'whats', 'when', 'where', 'whether', 'which', 'while', 'who', 'whom', 'whose',
        'why', 'will', 'with', 'within', 'without', 'won', 'wont', 'would', 'wouldn', 'yeah', 'yes', 'yet', 'you',
        'your', 'yours', 'yourself', 'yourselves',
    ];

    /** @var array<string, true>|null */
    protected ?array $set = null;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    /** The forum's own additions, as typed in the admin panel. */
    public function extraSetting(): string
    {
        return (string) $this->settings->get('ernestdefoe-kindred.extra_stopwords', '');
    }

    public function has(string $word): bool
    {
        if ($this->set === null) {
            $extra = preg_split('/[\s,;]+/u', mb_strtolower($this->extraSetting()), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $this->set = array_fill_keys(array_merge(self::SHIPPED, $extra), true);
        }

        return isset($this->set[$word]);
    }
}
