<?php

namespace Ernestdefoe\Kindred\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use Flarum\Settings\SettingsRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;

class SimilarTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-kindred');

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            Tag::class => [
                ['id' => 1, 'name' => 'Kitchen', 'slug' => 'kitchen', 'position' => 0],
                ['id' => 2, 'name' => 'Staff', 'slug' => 'staff', 'position' => 1, 'is_restricted' => true],
            ],
            Discussion::class => [
                $this->discussion(1, 'Sourdough bread starter recipe'),
                $this->discussion(2, 'My sourdough starter recipes', ['last_posted_at' => Carbon::now()->subDays(400)]),
                $this->discussion(3, 'Sourdough bread crust'),
                $this->discussion(4, 'Bicycle gears slipping'),
                $this->discussion(5, 'Sourdough starter recipe, hidden', ['hidden_at' => Carbon::now()]),
                $this->discussion(6, 'Sourdough starter recipe, private', ['is_private' => true]),
                $this->discussion(7, 'Staff sourdough starter recipe'),
                $this->discussion(8, 'Hidden sourdough itself', ['hidden_at' => Carbon::now()]),
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 3, 'tag_id' => 1],
                ['discussion_id' => 7, 'tag_id' => 2],
            ],
        ]);
    }

    private function discussion(int $id, string $title, array $extra = []): array
    {
        return $extra + [
            'id' => $id, 'title' => $title, 'slug' => Str::slug($title), 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(),
            'user_id' => 2, 'comment_count' => 3,
        ];
    }

    private bool $cacheCleared = false;

    private function similar(int $id, ?int $actor = null): array
    {
        // Lists are cached for hours; a run must never read another test's.
        if (! $this->cacheCleared) {
            $this->app()->getContainer()->make(Repository::class)->flush();
            $this->cacheCleared = true;
        }

        $response = $this->send($this->request('GET', "/api/kindred/$id", $actor ? ['authenticatedAs' => $actor] : []));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    /** For a forum already booted, where setting() no longer applies. */
    private function changeSetting(string $key, mixed $value): void
    {
        $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set($key, $value);
    }

    private function titles(int $id, ?int $actor = null): array
    {
        return array_column($this->similar($id, $actor)[1]['data'], 'title');
    }

    #[Test]
    public function a_discussion_the_reader_cannot_see_is_not_found()
    {
        $this->assertSame(404, $this->similar(8)[0], 'Hidden');
        $this->assertSame(404, $this->similar(7)[0], 'In a restricted tag');
        $this->assertSame(404, $this->similar(999)[0]);
    }

    #[Test]
    public function similar_discussions_come_best_first_and_only_those_the_reader_may_see()
    {
        [$status, $body] = $this->similar(1);

        $this->assertSame(200, $status);
        $this->assertSame(
            ['My sourdough starter recipes', 'Sourdough bread crust'],
            array_column($body['data'], 'title'),
            'Never hidden, private, restricted or unrelated; plurals match their singulars'
        );

        $first = $body['data'][0];
        $this->assertSame('2', $first['id']);
        $this->assertSame('2-my-sourdough-starter-recipes', $first['slug']);
        $this->assertSame(2, $first['replyCount']);

        $this->assertSame(['Kitchen'], array_column($body['data'][1]['tags'], 'name'), 'Each result shows its tags');
    }

    #[Test]
    public function the_shared_list_is_still_narrowed_to_each_reader()
    {
        // The admin's request fills the cache with what the admin may see.
        $this->assertContains('Staff sourdough starter recipe', $this->titles(1, 1));

        $this->assertNotContains('Staff sourdough starter recipe', $this->titles(1), 'A guest, reading the cached list');

        // Hidden discussions are left out of the list even for those who can see them.
        $this->assertNotContains('Sourdough starter recipe, hidden', $this->titles(1, 1));
    }

    #[Test]
    public function the_limit_and_maximum_age_settings_apply()
    {
        $this->setting('ernestdefoe-kindred.limit', 1);
        $this->assertCount(1, $this->titles(1));

        $this->changeSetting('ernestdefoe-kindred.limit', 5);
        $this->changeSetting('ernestdefoe-kindred.max_age_days', 30);
        $this->assertSame(['Sourdough bread crust'], $this->titles(1), 'The year-old discussion is left out');
    }

    #[Test]
    public function an_ignored_word_no_longer_makes_titles_similar()
    {
        $this->assertContains('My sourdough starter recipes', $this->titles(1));

        // The list was cached a moment ago; changing the ignored words redoes it.
        $this->changeSetting('ernestdefoe-kindred.extra_stopwords', 'sourdough, starter, recipe');

        $this->assertSame(['Sourdough bread crust'], $this->titles(1), 'Only "bread" is left in common');
    }

    #[Test]
    public function a_shared_tag_cannot_make_up_for_too_little_in_common()
    {
        $this->prepareDatabase([
            Discussion::class => [
                $this->discussion(30, 'Sourdough loaf hydration levels explained'),
                $this->discussion(31, 'Hydration for marathon runners'),
            ],
            'discussion_tag' => [
                ['discussion_id' => 30, 'tag_id' => 1],
                ['discussion_id' => 31, 'tag_id' => 1],
            ],
        ]);

        // One word in five is below the bar, however the tag and recency add up.
        $this->assertNotContains('Hydration for marathon runners', $this->titles(30));
    }

    #[Test]
    public function a_shared_tag_lifts_a_weaker_match_over_the_bar()
    {
        $this->prepareDatabase([
            Discussion::class => [
                $this->discussion(33, 'Rye flour hydration ratio'),
                $this->discussion(34, 'Rye whiskey'),
                $this->discussion(35, 'Rye whiskey cocktails'),
            ],
            'discussion_tag' => [
                ['discussion_id' => 33, 'tag_id' => 1],
                ['discussion_id' => 34, 'tag_id' => 1],
            ],
        ]);

        $titles = $this->titles(33);
        $this->assertContains('Rye whiskey', $titles, 'One word in four, in the same tag');
        $this->assertNotContains('Rye whiskey cocktails', $titles, 'One word in four, and nothing else in common');
    }

    #[Test]
    public function a_plural_finds_its_singular()
    {
        $this->prepareDatabase([
            Discussion::class => [
                $this->discussion(40, 'Cast iron pans'),
                $this->discussion(41, 'Seasoning a pan'),
            ],
        ]);

        $this->assertContains('Seasoning a pan', $this->titles(40));
    }

    #[Test]
    public function a_renamed_discussion_is_matched_again()
    {
        $this->assertContains('Sourdough bread crust', $this->titles(1, 1));

        $response = $this->send($this->request('PATCH', '/api/discussions/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['type' => 'discussions', 'id' => '1', 'attributes' => ['title' => 'Bicycle gears']]],
        ]));
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(['Bicycle gears slipping'], $this->titles(1, 1));
    }

    #[Test]
    public function many_results_take_a_fixed_number_of_queries()
    {
        $this->setting('ernestdefoe-kindred.limit', 20);
        $more = [];
        $tags = [];
        for ($id = 10; $id < 25; $id++) {
            $more[] = $this->discussion($id, "Sourdough starter recipe number $id");
            $tags[] = ['discussion_id' => $id, 'tag_id' => 1];
        }
        $this->prepareDatabase([Discussion::class => $more, 'discussion_tag' => $tags]);

        // The repeated-query detector fails the request on a per-result query.
        [$status, $body] = $this->similar(1);

        $this->assertSame(200, $status);
        $this->assertGreaterThan(15, count($body['data']));
    }

    #[Test]
    public function the_list_goes_below_the_discussion_by_default()
    {
        $this->assertFalse($this->forumAttribute('kindredSidebar'));
    }

    #[Test]
    public function the_forum_says_when_the_list_goes_in_the_sidebar()
    {
        $this->setting('ernestdefoe-kindred.sidebar', '1');

        $this->assertTrue($this->forumAttribute('kindredSidebar'));
    }

    private function forumAttribute(string $name): mixed
    {
        return json_decode((string) $this->send($this->request('GET', '/api'))->getBody(), true)['data']['attributes'][$name];
    }
}
