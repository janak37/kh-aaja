<?php

use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Shop;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->shop = Shop::factory()->create([
        'name' => 'Didi Ko Khaja Ghar',
        'whatsapp_number' => '9841234567',
    ]);
});

test('anyone can open a shop board without logging in', function () {
    Item::factory()->for($this->shop)->create(['name' => 'Jhol Momo']);

    $this->get(route('board.show', $this->shop))
        ->assertOk()
        ->assertSee('Didi Ko Khaja Ghar')
        ->assertSee('Jhol Momo');
});

test('the board address uses the shop slug', function () {
    expect(route('board.show', $this->shop))->toEndWith('/s/'.$this->shop->slug);
});

test('unknown and suspended shops are not found', function () {
    $this->get('/s/no-such-shop')->assertNotFound();

    $suspended = Shop::factory()->suspended()->create();
    $this->get(route('board.show', $suspended))->assertNotFound();
});

test('archived dishes are hidden', function () {
    Item::factory()->for($this->shop)->archived()->create(['name' => 'Old Dish']);

    $this->get(route('board.show', $this->shop))->assertDontSee('Old Dish');
});

test('today\'s statuses are shown', function () {
    $item = Item::factory()->for($this->shop)->create(['name' => 'Jhol Momo']);
    DailyStatus::factory()->for($item)->soldOut()->create();

    $this->get(route('board.show', $this->shop))->assertSee('Sold out');
});

test('yesterday\'s board is never shown as today\'s', function () {
    $item = Item::factory()->for($this->shop)->create(['name' => 'Jhol Momo']);
    DailyStatus::factory()->for($item)->soldOut()->create([
        'board_date' => now('Asia/Kathmandu')->subDay()->toDateString(),
    ]);

    $this->get(route('board.show', $this->shop))
        ->assertSee('Not updated today')
        ->assertDontSee('Sold out');
});

test('specials are shown above the menu', function () {
    Item::factory()->for($this->shop)->create(['name' => 'Samosa', 'sort_order' => 1]);
    $special = Item::factory()->for($this->shop)->create(['name' => 'Buff Momo', 'sort_order' => 2]);
    DailyStatus::factory()->for($special)->special('Only till 2 pm')->create();

    $this->get(route('board.show', $this->shop))
        ->assertSeeInOrder(["Today's specials", 'Buff Momo', 'Only till 2 pm', 'Menu', 'Samosa']);
});

test('whatsapp links have the dish name typed in', function () {
    Item::factory()->for($this->shop)->create(['name' => 'Jhol Momo']);

    $this->get(route('board.show', $this->shop))
        ->assertSee('https://wa.me/9779841234567?text=', false)
        ->assertSee('Jhol%20Momo', false);
});

test('the call button uses the shop phone', function () {
    $this->get(route('board.show', $this->shop))
        ->assertSee('tel:'.$this->shop->phone, false);
});

test('the board refreshes itself every 15 seconds', function () {
    $this->get(route('board.show', $this->shop))
        ->assertSee('wire:poll.15s', false);
});

test('open now is worked out in the shop timezone', function () {
    $shop = Shop::factory()->make(['opens_at' => '07:00:00', 'closes_at' => '20:00:00']);

    $this->travelTo(Carbon::parse('2026-11-05 03:00:00', 'UTC')); // 08:45 in Kathmandu
    expect($shop->isOpenNow())->toBeTrue();

    $this->travelTo(Carbon::parse('2026-11-05 15:00:00', 'UTC')); // 20:45 in Kathmandu
    expect($shop->isOpenNow())->toBeFalse();
});
