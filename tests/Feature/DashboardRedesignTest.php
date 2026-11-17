<?php

use App\Enums\UserRole;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-05 06:00:00', 'UTC'));
});

test('the owner dashboard shows today\'s reservations, loved dishes and the share link', function () {
    $owner = User::factory()->create();
    $owner->assignRole(UserRole::ShopOwner);
    $shop = Shop::factory()->for($owner, 'owner')->create();
    $item = Item::factory()->for($shop)->create(['name' => 'Jhol Momo', 'favourites_count' => 3]);

    Reservation::factory()->for($shop)->for($item)->create([
        'board_date' => '2026-11-05',
        'customer_name' => 'Sita',
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Latest reservations')
        ->assertSee('Sita')
        ->assertSee('Most loved dishes')
        ->assertSee('♥ 3')
        ->assertSee(route('board.show', $shop));
});

test('the admin dashboard shows today\'s numbers across Kh-aaja', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin);
    Shop::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Active shops')
        ->assertSee('Manage shops');
});
