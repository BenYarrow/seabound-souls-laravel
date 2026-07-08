<?php

// Persists a media item's focal point (x/y %) — called by the click-to-set
// interaction in the Filament MediaPicker preview.

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaFocalController extends Controller
{
    /**
     * Save the focal point for a media library item.
     *
     * Receives x/y as integers (0–100, percentage of image dimensions) from the
     * Alpine click handler in the MediaPicker preview card, validates them, and
     * persists them to the media_library row.
     *
     * @param  Request       $request
     * @param  MediaLibrary  $media
     * @return JsonResponse
     */
    public function store(Request $request, MediaLibrary $media): JsonResponse
    {
        $data = $request->validate([
            'x' => ['required', 'integer', 'between:0,100'],
            'y' => ['required', 'integer', 'between:0,100'],
        ]);

        $media->update(['focal_x' => $data['x'], 'focal_y' => $data['y']]);

        return response()->json(['focal_x' => $media->focal_x, 'focal_y' => $media->focal_y]);
    }
}
