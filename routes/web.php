<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MusicController;

Route::get('/', [MusicController::class, 'index']);
Route::get('/music/{filename}', [MusicController::class, 'stream']);
Route::get('/update-playlist', [MusicController::class, 'updatePlaylist']);
Route::post('/update-play-count', [MusicController::class, 'updatePlayCount']);
