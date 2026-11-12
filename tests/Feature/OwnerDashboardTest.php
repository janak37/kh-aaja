<?php

use App\Enums\ItemStatus;
use App\Enums\UserRole;
use App\Models\BoardUpdate;
use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // 11:45 am in Kathmandu, so "today" can't change mid-test.
    $this->travelTo(Carbon::parse('2026-11-05 06:00:00', 'UTC'));

    $this->owner = User::factory()->create(['name' => 'Janak Dangi']);
    $this->owner->assignRole(UserRole::ShopOwner);
});

test('an owner without a shop is asked to set it up', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Set up your shop')
        ->assertSee(route('shop.edit'));
});

test('an owner sees a greeting, their shop and the update button', function () {
    Shop::factory()->for($this->owner, 'owner')->create(['name' => 'Newa Momo Center']);

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertSee('Namaste, Janak')
        ->assertSee('Newa Momo Center')
        ->assertSee('Not updated yet today')
        ->assertSee('Set today&#039;s board', false)
        ->assertSee(route('shop.today'));
});

test('today\'s counts only include dishes still on the menu', function () {
    $shop = Shop::factory()->for($this->owner, 'owner')->create();
    $a = Item::factory()->for($shop)->create();
    $b = Item::factory()->for($shop)->create();
    Item::factory()->for($shop)->create();
    $archived = Item::factory()->for($shop)->archived()->create();

    DailyStatus::factory()->for($a)->create(['status' => ItemStatus::Available]);
    DailyStatus::factory()->for($b)->soldOut()->create();
    DailyStatus::factory()->for($archived)->create(['status' => ItemStatus::Available]);

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertSee('2 of 3 dishes set today');
});

test('the dashboard shows the streak', function () {
    $shop = Shop::factory()->for($this->owner, 'owner')->create();

    foreach ([0, 1, 2] as $daysAgo) {
        BoardUpdate::factory()->for($shop)->create([
            'board_date' => now('Asia/Kathmandu')->subDays($daysAgo)->toDateString(),
        ]);
    }

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertSee('3-day streak')
        ->assertSee('3 of 10 days for the Reliable board badge');
});

test('the getting started list shows until every step is done', function () {
    $shop = Shop::factory()->for($this->owner, 'owner')->create(['whatsapp_number' => null]);

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertSee('Getting started');

    $shop->update(['whatsapp_number' => '9841234567']);
    $items = Item::factory()->for($shop)->count(3)->create(['photo_path' => 'dishes/x.webp']);
    DailyStatus::factory()->for($items->first())->create();
    $this->owner->refresh();

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertDontSee('Getting started');
});

test('an admin sees a simple welcome without errors', function () {
    $admin = User::factory()->create(['name' => 'Admin']);
    $admin->assignRole(UserRole::Admin);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welcome to Kh-aaja.')
        ->assertDontSee('Set up your shop');
});
