<?php

use App\Models\Shop;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

test('the database seeder creates no logins outside local', function () {
    $this->seed();

    expect(User::count())->toBe(0);
});

test('the demo seeder builds fourteen shops with menus and an admin', function () {
    Storage::fake('public');

    $this->seed(DemoSeeder::class);

    expect(Shop::count())->toBe(14)
        ->and(Shop::first()->items()->count())->toBe(6)
        ->and(User::where('email', 'admin@example.com')->first()->hasRole('admin'))->toBeTrue();
});
