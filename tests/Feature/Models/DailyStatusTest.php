<?php

use App\Enums\ItemStatus;
use App\Models\DailyStatus;
use App\Models\Item;
use Illuminate\Database\QueryException;

test('status is cast to the ItemStatus enum', function () {
    $row = DailyStatus::factory()->soldOut()->create();

    expect($row->fresh()->status)->toBe(ItemStatus::SoldOut);
});

test('an item can only have one row per board date', function () {
    $item = Item::factory()->create();

    DailyStatus::factory()->for($item)->create(['board_date' => '2026-11-05']);
    DailyStatus::factory()->for($item)->create(['board_date' => '2026-11-05']);
})->throws(QueryException::class);

test('the same item can have rows on different days', function () {
    $item = Item::factory()->create();

    DailyStatus::factory()->for($item)->create(['board_date' => '2026-11-04']);
    DailyStatus::factory()->for($item)->create(['board_date' => '2026-11-05']);

    expect($item->dailyStatuses()->count())->toBe(2);
});
