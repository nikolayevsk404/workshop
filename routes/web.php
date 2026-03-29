<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Guest routes
Route::middleware('guest')->group(function () {
    Route::livewire('/register', 'pages::auth.register')->name('register');
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('password.request');
    Route::livewire('/reset-password/{token}', 'pages::auth.reset-password')->name('password.reset');
});

// Auth routes
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', function (Request $request) {
        auth()->guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::livewire('/arena', 'pages::arena.index')->name('arena');
    Route::livewire('/arena/new-match', 'pages::arena.match-setup')->name('arena.match-setup');
    Route::livewire('/arena/match/{match}', 'pages::arena.match-board')->name('arena.match.show');
    Route::livewire('/arena/match/{match}/results', 'pages::arena.match-results')->name('arena.match.results');
    Route::livewire('/leaderboard', 'pages::leaderboard.index')->name('leaderboard');
    Route::livewire('/settings', 'pages::settings.index')->name('settings');
});
