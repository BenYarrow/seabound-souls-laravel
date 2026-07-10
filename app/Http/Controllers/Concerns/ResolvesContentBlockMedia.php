<?php

// Trait used by controllers that pass content-block arrays to Inertia pages.
// Resolves referenced IDs inside block data — in batched queries, so no N+1:
//   • media IDs → focal-bearing imagePayload objects (`{key}_image`/`_images`),
//     so every <CoverImage> in content blocks can honour the focal point; and
//   • list-block picks (list_content_blogs / list_content_spot_guides) → the
//     published card entries (`{key}_resolved`), in authored order (drafts dropped).

namespace App\Http\Controllers\Concerns;

use App\Models\Blog;
use App\Models\MediaLibrary;
use App\Models\SpotGuide;

trait ResolvesContentBlockMedia
{
    /**
     * Walk a content-block array, resolve every referenced media ID to a
     * focal-bearing `imagePayload()` object, and attach it back to block data.
     *
     * Single-ID keys  → `{$key}_image`  (replaces the old `{$key}_url`)
     * Array-ID keys   → `{$key}_images` (replaces the old `{$key}_urls`)
     *
     * One batched DB query covers all blocks.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    protected function resolveContentBlockMedia(array $blocks): array
    {
        $mediaIdKeys = ['media_library_id', 'imageLeftMediaId', 'imageRightMediaId', 'backgroundImageMediaId'];
        $mediaArrayKeys = ['mediaIds'];

        // Collect all IDs needed across every block in one pass.
        $singleIds = [];
        $arrayIds = [];

        foreach ($blocks as $block) {
            $data = $block['data'] ?? [];
            foreach ($mediaIdKeys as $key) {
                if (!empty($data[$key])) {
                    $singleIds[] = (int) $data[$key];
                }
            }
            foreach ($mediaArrayKeys as $key) {
                if (!empty($data[$key]) && is_array($data[$key])) {
                    $arrayIds = array_merge($arrayIds, array_map('intval', $data[$key]));
                }
            }
        }

        $allIds = array_unique(array_merge($singleIds, $arrayIds));
        $mediaMap = !empty($allIds)
            ? MediaLibrary::whereIn('id', $allIds)->get()->keyBy('id')
            : collect();

        // Collect list-block entry IDs so we can resolve them published-only in
        // one batched query per type (drafts / deleted IDs drop out; authored
        // order is preserved when mapping back below).
        $blogIds = [];
        $spotGuideIds = [];
        foreach ($blocks as $block) {
            $data = $block['data'] ?? [];
            if (($block['type'] ?? '') === 'list_content_blogs' && is_array($data['customBlogEntries'] ?? null)) {
                $blogIds = array_merge($blogIds, array_map('intval', $data['customBlogEntries']));
            }
            if (($block['type'] ?? '') === 'list_content_spot_guides' && is_array($data['customSpotGuideEntries'] ?? null)) {
                $spotGuideIds = array_merge($spotGuideIds, array_map('intval', $data['customSpotGuideEntries']));
            }
        }

        $blogMap = !empty($blogIds)
            ? Blog::published()->whereIn('id', array_unique($blogIds))->with('thumbnailMedia')->get()->keyBy('id')
            : collect();
        $spotGuideMap = !empty($spotGuideIds)
            ? SpotGuide::published()->whereIn('id', array_unique($spotGuideIds))->with(['country', 'thumbnailMedia'])->get()->keyBy('id')
            : collect();

        return array_map(function (array $block) use ($mediaIdKeys, $mediaArrayKeys, $mediaMap, $blogMap, $spotGuideMap) {
            $data = $block['data'] ?? [];

            // Single-ID keys: emit a focal-bearing imagePayload object under `{key}_image`.
            foreach ($mediaIdKeys as $key) {
                if (!empty($data[$key])) {
                    $item = $mediaMap->get((int) $data[$key]);
                    $data[$key . '_image'] = $item ? $item->imagePayload() : null;
                }
            }

            // Array keys: emit an array of imagePayload objects under `{key}_images`.
            foreach ($mediaArrayKeys as $key) {
                if (!empty($data[$key]) && is_array($data[$key])) {
                    $data[$key . '_images'] = collect($data[$key])
                        ->map(fn ($id) => $mediaMap->get((int) $id))
                        ->filter()
                        ->map(fn ($m) => $m->imagePayload())
                        ->values()
                        ->toArray();
                }
            }

            // List-content blocks: resolve picked IDs to published card entries in
            // authored order. FeaturedGrid renders nothing for an empty array.
            if (($block['type'] ?? '') === 'list_content_blogs' && is_array($data['customBlogEntries'] ?? null)) {
                $data['customBlogEntries_resolved'] = collect($data['customBlogEntries'])
                    ->map(fn ($id) => $blogMap->get((int) $id))
                    ->filter()
                    ->map(fn ($blog) => [
                        'id' => $blog->id,
                        'title' => $blog->title,
                        'slug' => $blog->slug,
                        'thumbnail' => $blog->thumbnailMedia?->imagePayload(),
                    ])
                    ->values()
                    ->toArray();
            }
            if (($block['type'] ?? '') === 'list_content_spot_guides' && is_array($data['customSpotGuideEntries'] ?? null)) {
                $data['customSpotGuideEntries_resolved'] = collect($data['customSpotGuideEntries'])
                    ->map(fn ($id) => $spotGuideMap->get((int) $id))
                    ->filter()
                    ->map(fn ($guide) => [
                        'id' => $guide->id,
                        'title' => $guide->title,
                        'slug' => $guide->slug,
                        'thumbnail' => $guide->thumbnailMedia?->imagePayload(),
                        'subtitle' => $guide->country?->name,
                    ])
                    ->values()
                    ->toArray();
            }

            $block['data'] = $data;

            return $block;
        }, $blocks);
    }
}
