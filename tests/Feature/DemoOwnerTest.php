<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

test('the demo seeder creates a fixed owner login with a shop', function () {
    Storage::fake('public');

    $this->seed(DemoSeeder::class);

    $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();

    expect($owner->hasRole(UserRole::ShopOwner))->toBeTrue()
        ->and($owner->shop?->name)->toBe('Himalayan Momo Center')
        ->and($owner->shop?->items()->first()?->photo_path)->toBe('dishes/demo/jhol-momo.webp');

    Storage::disk('public')->assertExists('dishes/demo/jhol-momo.webp');
});

test('the demo seeder puts one shop in every area with a menu that fits', function () {
    Storage::fake('public');

    $this->seed(DemoSeeder::class);

    expect(Shop::query()->pluck('area')->sort()->values()->all())
        ->toBe(collect(config('khaaja.areas'))->sort()->values()->all());

    $bakery = Shop::query()->where('category', 'bakery')->firstOrFail();

    expect($bakery->items()->pluck('name'))->not->toContain('Jhol Momo')
        ->and($bakery->items()->pluck('name'))->toContain('Croissant');
});
