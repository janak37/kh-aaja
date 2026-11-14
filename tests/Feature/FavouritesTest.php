<?php

use App\Models\BoardUpdate;
use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Shop;
use App\Services\FavouriteCounter;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // 11:45 am in Kathmandu on 5 Nov.
    $this->travelTo(Carbon::parse('2026-11-05 06:00:00', 'UTC'));
    $this->today = '2026-11-05';
});

// Counting

test('a visitor adds at most one heart per shop', function () {
    $shop = Shop::factory()->create();
    $visitor = new FavouriteCounter('visitor-a');

    expect($visitor->record('shop', $shop->id, true))->toBeTrue()
        ->and($visitor->record('shop', $shop->id, true))->toBeFalse()
        ->and($shop->fresh()->favourites_count)->toBe(1);
});

test('different visitors each count', function () {
    $shop = Shop::factory()->create();

    (new FavouriteCounter('visitor-a'))->record('shop', $shop->id, true);
    (new FavouriteCounter('visitor-b'))->record('shop', $shop->id, true);

    expect($shop->fresh()->favourites_count)->toBe(2);
});

test('only a heart the visitor gave can be taken back', function () {
    $shop = Shop::factory()->create();
    $a = new FavouriteCounter('visitor-a');
    $b = new FavouriteCounter('visitor-b');

    $a->record('shop', $shop->id, true);

    expect($b->record('shop', $shop->id, false))->toBeFalse()
        ->and($a->record('shop', $shop->id, false))->toBeTrue()
        ->and($shop->fresh()->favourites_count)->toBe(0);
});

test('a dish heart is counted on that dish, not the shop', function () {
    $item = Item::factory()->create();

    (new FavouriteCounter('visitor-a'))->record('dish', $item->id, true);

    expect($item->fresh()->favourites_count)->toBe(1)
        ->and($item->shop->fresh()->favourites_count)->toBe(0);
});

test('suspended shops, archived dishes and unknown types are ignored', function () {
    $suspended = Shop::factory()->suspended()->create();
    $archived = Item::factory()->archived()->create();
    $visitor = new FavouriteCounter('visitor-a');

    expect($visitor->record('shop', $suspended->id, true))->toBeFalse()
        ->and($visitor->record('dish', $archived->id, true))->toBeFalse()
        ->and($visitor->record('user', 1, true))->toBeFalse();
});

test('a visitor can make only 30 changes a minute', function () {
    $shop = Shop::factory()->create();
    $visitor = new FavouriteCounter('visitor-a');

    foreach (range(1, FavouriteCounter::MAX_CHANGES_PER_MINUTE) as $i) {
        $visitor->record('shop', $shop->id, $i % 2 === 1); // on, off, on, off…
    }

    expect($visitor->record('shop', $shop->id, true))->toBeFalse()
        ->and($shop->fresh()->favourites_count)->toBe(0);
});

// The Favourites page

test('the favourites page opens for guests', function () {
    $this->get(route('favourites'))->assertOk()->assertSee('Favourites');
});

test('saved shops are listed, suspended ones are not', function () {
    $open = Shop::factory()->create(['name' => 'Momo Ghar']);
    $gone = Shop::factory()->suspended()->create(['name' => 'Gone Shop']);

    Livewire::test('pages::favourites')
        ->call('load', [$open->id, $gone->id], [])
        ->assertSee('Momo Ghar')
        ->assertDontSee('Gone Shop');
});

test('a saved dish shows the shops that have it available today', function () {
    [$fresh, $soldOut, $stale] = [
        Shop::factory()->create(['name' => 'Fresh Kitchen']),
        Shop::factory()->create(['name' => 'Empty Kitchen']),
        Shop::factory()->create(['name' => 'Sleepy Kitchen']),
    ];

    $items = collect([$fresh, $soldOut, $stale])
        ->map(fn (Shop $shop) => Item::factory()->for($shop)->create(['name' => 'Jhol Momo']));

    BoardUpdate::factory()->for($fresh)->create(['board_date' => $this->today]);
    BoardUpdate::factory()->for($soldOut)->create(['board_date' => $this->today]);
    DailyStatus::factory()->for($items[1])->soldOut()->create(['board_date' => $this->today]);

    Livewire::test('pages::favourites')
        ->call('load', [], [$items[0]->dish_key])
        ->assertSee('Fresh Kitchen')        // updated today, available
        ->assertDontSee('Empty Kitchen')    // sold out today
        ->assertDontSee('Sleepy Kitchen');  // didn't update today
});

test('only sensible values from the browser are kept', function () {
    Livewire::test('pages::favourites')
        ->call('load', [5, '6', -1, 5, null], ['jhol-momo', 7, '', str_repeat('x', 200)])
        ->assertSet('shopIds', [5])
        ->assertSet('dishKeys', ['jhol-momo']);
});

test('a heart tapped on the page is counted', function () {
    $shop = Shop::factory()->create();

    Livewire::test('pages::favourites')->call('favourite', 'shop', $shop->id, true);

    expect($shop->fresh()->favourites_count)->toBe(1);
});
// The homepage

test('the homepage links to favourites and shows heart counts', function () {
    Shop::factory()->create(['name' => 'Loved Shop', 'favourites_count' => 24]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('favourites'))
        ->assertSeeInOrder(['Loved Shop', '24']);
});

test('a heart tapped on the homepage is counted', function () {
    $shop = Shop::factory()->create();

    Livewire::test('pages::home')->call('favourite', 'shop', $shop->id, true);

    expect($shop->fresh()->favourites_count)->toBe(1);
});
// The board

test('the board links to favourites and shows the shop\'s heart count', function () {
    $shop = Shop::factory()->create(['favourites_count' => 24]);

    $this->get(route('board.show', $shop))
        ->assertOk()
        ->assertSee(route('favourites'))
        ->assertSeeInOrder([$shop->name, '24']);
});

test('a dish heart tapped on the board is counted', function () {
    $item = Item::factory()->create();

    Livewire::test('pages::board.show', ['shop' => $item->shop])
        ->call('favourite', 'dish', $item->id, true);

    expect($item->fresh()->favourites_count)->toBe(1);
});
