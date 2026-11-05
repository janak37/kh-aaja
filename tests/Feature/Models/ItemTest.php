<?php

use App\Models\Item;
use App\Models\Shop;

test('dish key is made from the item name', function () {
    $item = Item::factory()->create(['name' => 'Jhol Momo']);

    expect($item->dish_key)->toBe('jhol-momo');
});

test('dish key is never empty for nepali names', function () {
    $item = Item::factory()->create(['name' => 'झोल मोमो']);

    expect($item->dish_key)->not->toBe('');
});

test('active items exclude archived ones', function () {
    $shop = Shop::factory()->create();
    Item::factory()->for($shop)->create(['name' => 'Samosa']);
    Item::factory()->for($shop)->archived()->create(['name' => 'Old Dish']);

    expect($shop->items()->active()->pluck('name')->all())->toBe(['Samosa']);
});
