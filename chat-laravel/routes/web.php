<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';

Route::view('chat', 'chat')
    ->middleware(['auth', 'verified'])
    ->name('chat');

use App\Http\Controllers\SsoController;

Route::get('/sso', [SsoController::class, 'login']);