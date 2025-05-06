<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\DeviceController;
use App\Models\Song; // Song 모델을 추가하여 가사 조회
use App\Http\Controllers\LyricsController;

Route::get('/', [MusicController::class, 'index']);
Route::get('/get-favorite', [WelcomeController::class, 'index']);
Route::post('/toggle-favorite', [MusicController::class, 'toggleFavorite']);
Route::get('/favorites', [MusicController::class, 'listFavorites']);
Route::get('/music/{filename}', [MusicController::class, 'stream']);
Route::get('/update-playlist', [MusicController::class, 'updatePlaylist']);
Route::post('/update-play-count', [MusicController::class, 'updatePlayCount']);
Route::get('/search', [MusicController::class, 'search']);
Route::post('/register-device', [DeviceController::class, 'registerDevice']);
Route::get('/get-devices', [DeviceController::class, 'getDevices']);
Route::get('/get-available-devices', [DeviceController::class, 'getAvailableDevices']); // 선택사항, 프론트 요청에 맞게 별도 제공
Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('login', 'Auth\LoginController@login');
Route::post('logout', 'Auth\LoginController@logout')->name('logout');
Route::get('register', 'Auth\RegisterController@showRegistrationForm')->name('register');
Route::post('register', 'Auth\RegisterController@register');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// 가사 조회 라우트 수정: LyricsController를 통해 가사 조회
Route::get('/lyrics/{id}', [LyricsController::class, 'show']);
