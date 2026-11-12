<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

Route::middleware(['auth', RoleMiddleware::using(UserRole::Admin->value)])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::redirect('/', '/admin/shops');
        Route::livewire('shops', 'pages::admin.shops')->name('shops');
    });
