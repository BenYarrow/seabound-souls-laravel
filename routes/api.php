<?php

use App\Http\Controllers\Api\LiveWeatherController;
use App\Http\Controllers\Api\WeatherDataController;
use Illuminate\Support\Facades\Route;

Route::post('/live-weather', [LiveWeatherController::class, 'fetch'])->name('api.live-weather');
Route::get('/weather-data', [WeatherDataController::class, 'index'])->name('api.weather-data.index');
Route::get('/weather-data/{spotGuide}', [WeatherDataController::class, 'show'])->name('api.weather-data.show');
