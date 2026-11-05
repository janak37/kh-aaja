<?php

use App\Models\Shop;
use App\Models\User;
use Database\Seeders\DemoSeeder;

test('the database seeder creates no logins outside local', function () {
    $this->seed();

    expect(User::count())->toBe(0);
});

test('the demo seeder builds six shops with menus and an admin', function () {
    $this->seed(DemoSeeder::class);

    expect(Shop::count())->toBe(6)
        ->and(Shop::first()->items()->count())->toBe(8)
        ->and(User::where('email', 'admin@example.com')->first()->hasRole('admin'))->toBeTrue();
});
