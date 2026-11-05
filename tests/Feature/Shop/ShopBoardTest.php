<?php

use App\Enums\ItemStatus;
use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Shop;
use App\Services\ShopBoard;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->board = new ShopBoard($this->shop);
});

test('setting a status creates today\'s row and marks the board updated', function () {
    $item = Item::factory()->for($this->shop)->create();

    $this->board->setStatus($item, ItemStatus::Low);

    expect($this->board->statuses()->get($item->id)->status)->toBe(ItemStatus::Low)
        ->and($this->shop->boardUpdates()->count())->toBe(1);
});

test('changing a status twice keeps one row for the day', function () {
    $item = Item::factory()->for($this->shop)->create();

    $this->board->setStatus($item, ItemStatus::Low);
    $this->board->setStatus($item, ItemStatus::SoldOut);

    expect(DailyStatus::count())->toBe(1)
        ->and($this->board->statuses()->get($item->id)->status)->toBe(ItemStatus::SoldOut)
        ->and($this->shop->boardUpdates()->count())->toBe(1);
});

test('today is the shop\'s local date at 23:59 and 00:01 Kathmandu time', function () {
    $item = Item::factory()->for($this->shop)->create();

    $this->travelTo(Carbon::parse('2026-11-05 18:14:00', 'UTC')); // 23:59 in Kathmandu
    $this->board->setStatus($item, ItemStatus::Available);

    $this->travelTo(Carbon::parse('2026-11-05 18:16:00', 'UTC')); // 00:01 next day in Kathmandu
    $this->board->setStatus($item, ItemStatus::SoldOut);

    expect(DailyStatus::orderBy('board_date')->get()->map(fn ($row) => $row->board_date->toDateString())->all())
        ->toBe(['2026-11-05', '2026-11-06']);
});

test('only three specials are allowed per day', function () {
    $items = Item::factory()->for($this->shop)->count(4)->create();

    $items->take(3)->each(fn (Item $item) => $this->board->toggleSpecial($item));

    expect(fn () => $this->board->toggleSpecial($items->last()))->toThrow(DomainException::class)
        ->and($this->board->specialsCount())->toBe(3);
});

test('unstarring a special frees a slot', function () {
    $items = Item::factory()->for($this->shop)->count(4)->create();
    $items->take(3)->each(fn (Item $item) => $this->board->toggleSpecial($item));

    $this->board->toggleSpecial($items->first());
    $this->board->toggleSpecial($items->last());

    expect($this->board->specialsCount())->toBe(3);
});

test('copy yesterday copies status, price and specials', function () {
    $item = Item::factory()->for($this->shop)->create();
    DailyStatus::factory()->for($item)->create([
        'board_date' => $this->board->yesterday(),
        'status' => ItemStatus::Low,
        'price' => 200,
        'is_special' => true,
    ]);

    expect($this->board->copyYesterday())->toBe(1);

    $today = $this->board->statuses()->get($item->id);

    expect($today->status)->toBe(ItemStatus::Low)
        ->and($today->price)->toBe(200)
        ->and($today->is_special)->toBeTrue();
});

test('reset all marks every active dish available', function () {
    $a = Item::factory()->for($this->shop)->create();
    Item::factory()->for($this->shop)->create();
    $this->board->setStatus($a, ItemStatus::SoldOut);

    expect($this->board->resetAllToAvailable())->toBe(2)
        ->and($this->board->statuses()->pluck('status')->unique()->values()->all())->toBe([ItemStatus::Available]);
});

test('another shop\'s dish is refused', function () {
    $this->board->setStatus(Item::factory()->create(), ItemStatus::Low);
})->throws(NotFoundHttpException::class);

test('an archived dish is refused', function () {
    $item = Item::factory()->for($this->shop)->archived()->create();

    $this->board->setStatus($item, ItemStatus::Low);
})->throws(NotFoundHttpException::class);
