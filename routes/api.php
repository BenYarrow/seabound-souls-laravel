<?php

use App\Http\Controllers\Api\LiveWeatherController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\WeatherDataController;
use Illuminate\Support\Facades\Route;

// Search suggestions are typing-driven, so they get a higher ceiling than the
// weather endpoints (which proxy an external, keyed API).
Route::get('/search', [SearchController::class, 'index'])
    ->middleware('throttle:search')
    ->name('api.search');

Route::middleware('throttle:weather-api')->group(function () {
    Route::post('/live-weather', [LiveWeatherController::class, 'fetch'])->name('api.live-weather');
    Route::get('/weather-data', [WeatherDataController::class, 'index'])->name('api.weather-data.index');
    Route::get('/weather-data/{spotGuide}', [WeatherDataController::class, 'show'])->name('api.weather-data.show');
});
