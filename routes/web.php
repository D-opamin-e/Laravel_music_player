<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\DeviceController;

Route::get('/', [MusicController::class, 'index']);
Route::get('/get-favorite', [WelcomeController::class, 'index']);
Route::post('/toggle-favorite', [MusicController::class, 'toggleFavorite']);
Route::get('/favorites', [MusicController::class, 'listFavorites']);
Route::get('/music/{filename}', [MusicController::class, 'stream']);
Route::get('/update-playlist', [MusicController::class, 'updatePlaylist']);
Route::post('/update-play-count', [MusicController::class, 'updatePlayCount']);
Route::get('/search', [MusicController::class, 'search']);

// 📡 원격 제어 관련 라우트
Route::post('/register-device', [DeviceController::class, 'registerDevice']);
Route::get('/get-devices', [DeviceController::class, 'getDevices']);
Route::get('/get-available-devices', [DeviceController::class, 'getAvailableDevices']); // 선택사항, 프론트 요청에 맞게 별도 제공
