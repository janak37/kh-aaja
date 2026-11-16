<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoSeeder;

test('the demo seeder creates a fixed owner login with a shop', function () {
    $this->seed(DemoSeeder::class);

    $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();

    expect($owner->hasRole(UserRole::ShopOwner))->toBeTrue()
        ->and($owner->shop)->not->toBeNull();
});
