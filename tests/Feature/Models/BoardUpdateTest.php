<?php

use App\Models\BoardUpdate;
use App\Models\Shop;
use Illuminate\Database\QueryException;

test('a shop can only have one board update row per day', function () {
    $shop = Shop::factory()->create();

    BoardUpdate::factory()->for($shop)->create(['board_date' => '2026-11-05']);
    BoardUpdate::factory()->for($shop)->create(['board_date' => '2026-11-05']);
})->throws(QueryException::class);

test('a shop can have board updates on different days', function () {
    $shop = Shop::factory()->create();

    BoardUpdate::factory()->for($shop)->create(['board_date' => '2026-11-04']);
    BoardUpdate::factory()->for($shop)->create(['board_date' => '2026-11-05']);

    expect($shop->boardUpdates()->count())->toBe(2);
});
