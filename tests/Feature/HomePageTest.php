<?php

use App\Enums\ItemStatus;
use App\Models\BoardUpdate;
use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // 11:45 am in Kathmandu, so "today" can't change mid-test.
    $this->travelTo(Carbon::parse('2026-11-05 06:00:00', 'UTC'));
});

/**
 * A shop that updated its board today, with every dish available.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<int, string>  $dishes
 */
function shopUpdatedToday(array $attributes = [], array $dishes = ['Jhol Momo']): Shop
{
    $shop = Shop::factory()->create($attributes);

    foreach ($dishes as $position => $name) {
        $item = Item::factory()->for($shop)->create(['name' => $name, 'sort_order' => $position]);
        DailyStatus::factory()->for($item)->create(['status' => ItemStatus::Available]);
    }

    BoardUpdate::factory()->for($shop)->create();

    return $shop;
}

test('guests see the homepage with a sign-up link for shops', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('What can you eat nearby right now?')
        ->assertSee(route('register'));
});

test('shops updated today come before shops that did not update', function () {
    Shop::factory()->create(['name' => 'Sleepy Shop']);
    shopUpdatedToday(['name' => 'Fresh Shop']);

    $this->get('/')
        ->assertSeeInOrder(['Fresh Shop', 'Sleepy Shop'])
        ->assertSee('Not updated today');
});

test('suspended shops never appear', function () {
    shopUpdatedToday(['name' => 'Hidden Shop', 'suspended_at' => now()]);

    $this->get('/')->assertDontSee('Hidden Shop');
});

test('today\'s specials from all shops are shown', function () {
    $shop = Shop::factory()->create(['name' => 'Newa Momo Center']);
    $item = Item::factory()->for($shop)->create(['name' => 'Buff Jhol Momo']);
    DailyStatus::factory()->for($item)->special()->create();
    BoardUpdate::factory()->for($shop)->create();

    $this->get('/')->assertSeeInOrder(["Today's specials", 'Buff Jhol Momo', 'Newa Momo Center']);
});

test('yesterday\'s and sold-out specials are left out', function () {
    $shop = Shop::factory()->create();
    $old = Item::factory()->for($shop)->create(['name' => 'Old Special']);
    $gone = Item::factory()->for($shop)->create(['name' => 'Gone Special']);

    DailyStatus::factory()->for($old)->special()->create([
        'board_date' => now('Asia/Kathmandu')->subDay()->toDateString(),
    ]);
    DailyStatus::factory()->for($gone)->special()->soldOut()->create();

    $this->get('/')
        ->assertDontSee('Old Special')
        ->assertDontSee('Gone Special');
});

test('searching by dish name finds the shop that has it', function () {
    shopUpdatedToday(['name' => 'Momo Palace'], ['Jhol Momo']);
    shopUpdatedToday(['name' => 'Tea House'], ['Milk Tea']);

    Livewire::test('pages::home')
        ->set('q', 'milk')
        ->assertSee('Tea House')
        ->assertDontSee('Momo Palace');
});

test('the area filter shows only shops in that area', function () {
    shopUpdatedToday(['name' => 'Patan Shop', 'area' => 'Patan']);
    shopUpdatedToday(['name' => 'Thamel Shop', 'area' => 'Thamel']);

    Livewire::test('pages::home')
        ->set('area', 'Patan')
        ->assertSee('Patan Shop')
        ->assertDontSee('Thamel Shop');
});

test('tapping a category filters, tapping it again clears it', function () {
    shopUpdatedToday(['name' => 'Bread Corner', 'category' => 'bakery']);
    shopUpdatedToday(['name' => 'Momo Stop', 'category' => 'momo']);

    Livewire::test('pages::home')
        ->call('toggleCategory', 'bakery')
        ->assertSee('Bread Corner')
        ->assertDontSee('Momo Stop')
        ->call('toggleCategory', 'bakery')
        ->assertSee('Momo Stop');
});

test('an unknown area in the address is ignored', function () {
    shopUpdatedToday(['name' => 'Any Shop']);

    Livewire::test('pages::home')
        ->set('area', 'Mars')
        ->assertSee('Any Shop');
});
