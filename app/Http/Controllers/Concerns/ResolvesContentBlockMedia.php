<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MediaLibrary;

trait ResolvesContentBlockMedia
{
    protected function resolveContentBlockMedia(array $blocks): array
    {
        $mediaIdKeys = ['media_library_id', 'imageLeftMediaId', 'imageRightMediaId', 'backgroundImageMediaId'];
        $mediaArrayKeys = ['mediaIds'];

        // Collect all IDs needed
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

            foreach ($mediaIdKeys as $key) {
                if (!empty($data[$key])) {
                    $item = $mediaMap->get((int) $data[$key]);
                    $data[$key . '_url'] = $item ? $item->getUrl() : '';
                }
            }

            foreach ($mediaArrayKeys as $key) {
                if (!empty($data[$key]) && is_array($data[$key])) {
                    $data[$key . '_urls'] = collect($data[$key])
                        ->map(fn ($id) => $mediaMap->get((int) $id))
                        ->filter()
                        ->map(fn ($m) => ['url' => $m->getUrl(), 'alt' => $m->name])
                        ->values()
                        ->toArray();
                }
            }

            $block['data'] = $data;

            return $block;
        }, $blocks);
    }
}
