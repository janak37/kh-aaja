<?php

use App\Enums\UserRole;
use App\Http\Controllers\ShopQrController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

Route::middleware(['auth', RoleMiddleware::using(UserRole::ShopOwner->value)])->group(function () {
    Route::livewire('shop', 'pages::shop.profile')->name('shop.edit');
    Route::livewire('shop/menu', 'pages::shop.menu')->name('shop.menu');
    Route::livewire('shop/today', 'pages::shop.today')->name('shop.today');
    Route::livewire('shop/reservations', 'pages::shop.reservations')->name('shop.reservations');
    Route::livewire('shop/qr', 'pages::shop.qr')->name('shop.qr');

    Route::get('shop/qr.png', [ShopQrController::class, 'png'])->name('shop.qr.png');
    Route::get('shop/table-tent', [ShopQrController::class, 'tableTent'])->name('shop.table-tent');
});
