<?php

namespace Ernestdefoe\Kindred\Api;

use Carbon\Carbon;
use Ernestdefoe\Kindred\Matcher;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Http\RequestUtil;
use Flarum\Http\SlugManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/kindred/{id} — the discussions similar to this one that the reader
 * may see, best first. An empty list means "show nothing".
 */
class SimilarController implements RequestHandlerInterface
{
    public function __construct(
        protected Matcher $matcher,
        protected SettingsRepositoryInterface $settings,
        protected SlugManager $slugs,
        protected ExtensionManager $extensions,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $id = (int) Arr::get($request->getQueryParams(), 'id');

        // The reader must be able to see the discussion they're asking about;
        // otherwise this is a 404, the same as the discussion itself.
        $discussion = Discussion::whereVisibleTo($actor)->findOrFail($id);

        $candidates = $this->matcher->candidatesFor($discussion);

        if (! $candidates) {
            return new JsonResponse(['data' => []]);
        }

        $limit = max(1, min(20, (int) $this->settings->get('ernestdefoe-kindred.limit', 5) ?: 5));
        $maxAge = max(0, (int) $this->settings->get('ernestdefoe-kindred.max_age_days', 0));

        // Never trust the cache with permissions: what this reader may see is
        // asked fresh, every time.
        $query = Discussion::whereVisibleTo($actor)
            ->whereIn('discussions.id', array_keys($candidates))
            ->where('discussions.id', '!=', $discussion->id);

        if ($maxAge > 0) {
            $query->where('discussions.last_posted_at', '>=', Carbon::now()->subDays($maxAge));
        }

        $visible = $query->get()->keyBy('id');

        $rows = [];
        foreach (array_keys($candidates) as $candidateId) {
            if (isset($visible[$candidateId])) {
                $rows[] = $visible[$candidateId];
            }
            if (count($rows) >= $limit) {
                break;
            }
        }

        $tags = $this->extensions->isEnabled('flarum-tags') ? $this->visibleTags($rows, $actor) : [];

        return new JsonResponse([
            'data' => array_map(fn (Discussion $d) => [
                'id' => (string) $d->id,
                'title' => (string) $d->title,
                'slug' => $this->slugs->forResource(Discussion::class)->toSlug($d),
                'replyCount' => max(0, (int) $d->comment_count - 1),
                'lastPostedAt' => $d->last_posted_at?->toIso8601String(),
                'tags' => $tags[$d->id] ?? [],
            ], $rows),
        ]);
    }

    /**
     * The tags of each row that this reader may see, primary first.
     *
     * @param list<Discussion> $rows
     * @return array<int, list<array<string, mixed>>>
     */
    protected function visibleTags(array $rows, User $actor): array
    {
        if (! $rows) {
            return [];
        }

        $tagClass = \Flarum\Tags\Tag::class;
        $pivot = resolve('db')->table('discussion_tag')
            ->whereIn('discussion_id', array_map(fn ($d) => $d->id, $rows))
            ->get(['discussion_id', 'tag_id']);

        $tags = $tagClass::whereVisibleTo($actor)->whereIn('id', $pivot->pluck('tag_id')->unique()->all())->get()->keyBy('id');

        $out = [];
        foreach ($pivot as $row) {
            if ($tag = $tags[$row->tag_id] ?? null) {
                $out[(int) $row->discussion_id][] = $tag;
            }
        }

        foreach ($out as $discussionId => $list) {
            // Primary tags (those with a position) first, parents before children.
            usort($list, function ($a, $b) {
                $ap = $a->position === null ? 1 : 0;
                $bp = $b->position === null ? 1 : 0;

                return [$ap, $a->parent_id === null ? 0 : 1, $a->position ?? 0] <=> [$bp, $b->parent_id === null ? 0 : 1, $b->position ?? 0];
            });

            $out[$discussionId] = array_map(fn ($tag) => [
                'id' => (string) $tag->id,
                'name' => (string) $tag->name,
                'slug' => (string) $tag->slug,
                'color' => $tag->color ?: null,
                'icon' => $tag->icon ?: null,
                'isChild' => $tag->parent_id !== null,
            ], $list);
        }

        return $out;
    }
}
