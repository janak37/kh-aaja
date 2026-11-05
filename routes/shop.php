<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

Route::middleware(['auth', RoleMiddleware::using(UserRole::ShopOwner->value)])->group(function () {
    Route::livewire('shop', 'pages::shop.profile')->name('shop.edit');
    Route::livewire('shop/menu', 'pages::shop.menu')->name('shop.menu');
    Route::livewire('shop/today', 'pages::shop.today')->name('shop.today');
});
