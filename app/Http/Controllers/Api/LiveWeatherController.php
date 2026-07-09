<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LiveWeatherController extends Controller
{
    public function fetch(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $apiKey = config('services.openweathermap.key');
        $cacheKey = "live_weather_{$validated['lat']}_{$validated['lon']}";

        $data = cache()->remember($cacheKey, now()->addHour(), function () use ($validated, $apiKey) {
            $response = \Illuminate\Support\Facades\Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'lat' => $validated['lat'],
                'lon' => $validated['lon'],
                'appid' => $apiKey,
                'units' => 'metric',
            ]);

            return $response->json();
        });

        return response()->json($data);
    }
}
