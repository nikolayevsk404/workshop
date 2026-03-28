<?php

use Illuminate\Foundation\Auth\EmailVerificationRequest;
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
    // Email verification
    Route::livewire('/email/verify', 'pages::auth.verify-email')->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('arena');
    })->middleware('signed')->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Link de verificação reenviado!');
    })->middleware('throttle:6,1')->name('verification.send');

    // Logout
    Route::post('/logout', function (Request $request) {
        auth()->guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    // Protected routes (auth + verified)
    Route::middleware('verified')->group(function () {
        Route::livewire('/arena', 'pages::arena.index')->name('arena');
        Route::livewire('/arena/new-match', 'pages::arena.match-setup')->name('arena.match-setup');
        Route::livewire('/arena/match/{match}', 'pages::arena.match-show')->name('arena.match.show');
    });
});
