<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Public shop board: no login needed
Route::livewire('s/{shop}', 'pages::board.show')->name('board.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/shop.php';
require __DIR__.'/admin.php';
