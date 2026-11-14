<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');

// Public shop board: no login needed
Route::livewire('s/{shop}', 'pages::board.show')->name('board.show');
Route::livewire('favourites', 'pages::favourites')->name('favourites');
Route::livewire('privacy', 'pages::privacy')->name('privacy');
Route::livewire('s/{shop}/reserve/{item}', 'pages::board.reserve')->scopeBindings()->name('board.reserve');
Route::livewire('r/{reservation:public_token}', 'pages::reservation.show')->name('reservation.show');

// Language switch (English / नेपाली): no login needed, works for customers too
Route::get('lang/{locale}', LocaleController::class)->name('locale.switch');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/shop.php';
require __DIR__.'/admin.php';
