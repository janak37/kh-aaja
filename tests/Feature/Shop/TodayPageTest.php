<?php

use App\Enums\ItemStatus;
use App\Enums\UserRole;
use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Shop;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->assignRole(UserRole::ShopOwner);
    $this->shop = Shop::factory()->for($this->owner, 'owner')->create();
});

function todayPage(User $owner)
{
    return Livewire::actingAs($owner)->test('pages::shop.today');
}

test('guests are sent to the login page', function () {
    $this->get(route('shop.today'))->assertRedirect(route('login'));
});

test('an owner without a shop is sent to set it up first', function () {
    $newOwner = User::factory()->create();
    $newOwner->assignRole(UserRole::ShopOwner);

    todayPage($newOwner)->assertRedirect(route('shop.edit'));
});

test('the page lists only active dishes', function () {
    Item::factory()->for($this->shop)->create(['name' => 'Jhol Momo']);
    Item::factory()->for($this->shop)->archived()->create(['name' => 'Old Dish']);

    todayPage($this->owner)->assertSee('Jhol Momo')->assertDontSee('Old Dish');
});

test('an owner can mark a dish sold out', function () {
    $item = Item::factory()->for($this->shop)->create();

    todayPage($this->owner)->call('setStatus', $item->id, 'sold_out');

    expect(DailyStatus::where('item_id', $item->id)->first()->status)->toBe(ItemStatus::SoldOut);
});

test('an owner can set today\'s price', function () {
    $item = Item::factory()->for($this->shop)->create();

    todayPage($this->owner)->call('setPrice', $item->id, '250');

    expect(DailyStatus::where('item_id', $item->id)->first()->price)->toBe(250);
});

test('an invalid price is not saved', function () {
    $item = Item::factory()->for($this->shop)->create();

    todayPage($this->owner)->call('setPrice', $item->id, 'abc');

    expect(DailyStatus::count())->toBe(0);
});

test('an owner cannot change another shop\'s dish', function () {
    $otherItem = Item::factory()->create();

    todayPage($this->owner)->call('setStatus', $otherItem->id, 'low')->assertStatus(404);

    expect(DailyStatus::count())->toBe(0);
});

test('a fourth special is refused', function () {
    $items = Item::factory()->for($this->shop)->count(4)->create();
    $page = todayPage($this->owner);

    foreach ($items as $item) {
        $page->call('toggleSpecial', $item->id);
    }

    expect(DailyStatus::where('is_special', true)->count())->toBe(3);
});
