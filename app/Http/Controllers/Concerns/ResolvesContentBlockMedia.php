<?php

// Trait used by controllers that pass content-block arrays to Inertia pages.
// Resolves all media IDs referenced inside block data to focal-bearing imagePayload
// objects in a single batched query, so every <CoverImage> in content blocks can
// honour the focal point without N+1 queries.

namespace App\Http\Controllers\Concerns;

use App\Models\MediaLibrary;

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

        return array_map(function (array $block) use ($mediaIdKeys, $mediaArrayKeys, $mediaMap) {
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

            $block['data'] = $data;

            return $block;
        }, $blocks);
    }
}
