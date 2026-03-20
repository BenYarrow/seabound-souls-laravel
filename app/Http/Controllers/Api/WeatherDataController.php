<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SpotGuide;
use Illuminate\Http\JsonResponse;

class WeatherDataController extends Controller
{
    public function index(): JsonResponse
    {
        $data = cache()->remember('weather_data_all', now()->addHours(24), function () {
            return SpotGuide::published()
                ->with('weatherRecords')
                ->get()
                ->mapWithKeys(fn($guide) => [
                    $guide->slug => $this->formatWeatherRecords($guide),
                ]);
        });

        return response()->json($data);
    }

    public function show(SpotGuide $spotGuide): JsonResponse
    {
        $data = cache()->remember("weather_data_{$spotGuide->slug}", now()->addHours(24), function () use ($spotGuide) {
            $spotGuide->load('weatherRecords');
            return $this->formatWeatherRecords($spotGuide);
        });

        return response()->json($data);
    }

    private function formatWeatherRecords(SpotGuide $guide): array
    {
        return $guide->weatherRecords
            ->groupBy('year')
            ->map(fn($yearRecords) => $yearRecords->sortBy('month')->values()->map(fn($r) => [
                'month' => $r->month_name,
                'avgTemp' => (float) $r->avg_temp,
                'ktsWind' => (float) $r->kts_wind,
                'ktsGust' => (float) $r->kts_gust,
                'mphWind' => $r->mph_wind,
                'mphGust' => $r->mph_gust,
                'kphWind' => $r->kph_wind,
                'kphGust' => $r->kph_gust,
            ])->toArray())
            ->toArray();
    }
}
