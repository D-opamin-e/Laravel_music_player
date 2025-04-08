<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\WelcomeController;

Route::get('/', [MusicController::class, 'index']);
Route::get('/get-favorite', [WelcomeController::class, 'index']);
Route::post('/toggle-favorite', [MusicController::class, 'toggleFavorite']);
Route::get('/favorites', [MusicController::class, 'listFavorites']);
Route::get('/music/{filename}', [MusicController::class, 'stream']);
Route::get('/update-playlist', [MusicController::class, 'updatePlaylist']);
Route::post('/update-play-count', [MusicController::class, 'updatePlayCount']);
Route::get('/search', [MusicController::class, 'search']);
