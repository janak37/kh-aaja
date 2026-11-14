<?php

use App\Enums\DeclineReason;
use App\Enums\ItemStatus;
use App\Enums\ReservationStatus;
use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // 11:45 am in Kathmandu, so "today" can't change mid-test.
    $this->travelTo(Carbon::parse('2026-11-05 06:00:00', 'UTC'));

    $this->shop = Shop::factory()->create(['name' => 'Newa Momo Center']);
    $this->item = Item::factory()->for($this->shop)->create(['name' => 'Jhol Momo']);
    DailyStatus::factory()->for($this->item)->create(['status' => ItemStatus::Available]);
});

function reserveForm(Shop $shop, Item $item)
{
    return Livewire::test('pages::board.reserve', ['shop' => $shop, 'item' => $item]);
}

function filledReservation(Shop $shop, Item $item)
{
    return reserveForm($shop, $item)
        ->set('name', 'Sita')
        ->set('phone', '9841234567')
        ->set('quantity', '2')
        ->set('consent', true);
}

// The board

test('the board shows a reserve link only for dishes available today', function () {
    $unset = Item::factory()->for($this->shop)->create(['name' => 'Samosa']);
    $soldOut = Item::factory()->for($this->shop)->create(['name' => 'Sel Roti']);
    DailyStatus::factory()->for($soldOut)->soldOut()->create();

    $this->get(route('board.show', $this->shop))
        ->assertSee(route('board.reserve', [$this->shop, $this->item]))
        ->assertDontSee(route('board.reserve', [$this->shop, $unset]))
        ->assertDontSee(route('board.reserve', [$this->shop, $soldOut]));
});

// The form

test('a customer can reserve a dish and lands on the status page', function () {
    $page = filledReservation($this->shop, $this->item)->call('reserve')->assertHasNoErrors();

    $reservation = Reservation::sole();

    expect($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->shop_id)->toBe($this->shop->id)
        ->and($reservation->item_id)->toBe($this->item->id)
        ->and($reservation->quantity)->toBe(2)
        ->and($reservation->customer_phone)->toBe('9841234567')
        ->and($reservation->consent_at)->not->toBeNull()
        ->and($reservation->board_date->toDateString())->toBe('2026-11-05');

    $page->assertRedirect(route('reservation.show', $reservation->public_token));
});

test('consent is required', function () {
    filledReservation($this->shop, $this->item)
        ->set('consent', false)
        ->call('reserve')
        ->assertHasErrors(['consent']);

    expect(Reservation::count())->toBe(0);
});

test('only nepali mobile numbers are accepted', function () {
    filledReservation($this->shop, $this->item)
        ->set('phone', '12345')
        ->call('reserve')
        ->assertHasErrors(['phone']);

    expect(Reservation::count())->toBe(0);
});

test('a dish that sold out meanwhile cannot be reserved', function () {
    $page = filledReservation($this->shop, $this->item);

    DailyStatus::query()->update(['status' => ItemStatus::SoldOut]);

    $page->call('reserve')->assertHasErrors(['quantity']);

    expect(Reservation::count())->toBe(0);
});

test('another shop\'s dish cannot be reserved through this shop', function () {
    $otherItem = Item::factory()->create();

    $this->get(route('board.reserve', [$this->shop, $otherItem]))->assertNotFound();
});

test('a filled honeypot saves nothing', function () {
    filledReservation($this->shop, $this->item)
        ->set('website', 'http://spam.example')
        ->call('reserve');

    expect(Reservation::count())->toBe(0);
});

test('one address can only reserve 5 times in 10 minutes', function () {
    foreach (range(1, 5) as $attempt) {
        filledReservation($this->shop, $this->item)->call('reserve')->assertHasNoErrors();
    }

    filledReservation($this->shop, $this->item)->call('reserve')->assertHasErrors(['phone']);

    expect(Reservation::count())->toBe(5);
});

// The status page

test('the status page shows the answer but never the phone number', function () {
    $reservation = Reservation::factory()->for($this->shop)->for($this->item)->create([
        'customer_phone' => '9841234567',
    ]);

    $this->get(route('reservation.show', $reservation->public_token))
        ->assertOk()
        ->assertSee('Waiting for Newa Momo Center to confirm')
        ->assertSee('Jhol Momo')
        ->assertSee('wire:poll.15s', false)
        ->assertDontSee('9841234567');
});

test('a declined reservation shows the reason and stops refreshing', function () {
    $reservation = Reservation::factory()->for($this->shop)->for($this->item)
        ->declined(DeclineReason::SoldOut)
        ->create();

    $this->get(route('reservation.show', $reservation->public_token))
        ->assertSee('Declined: Sold out')
        ->assertDontSee('wire:poll', false);
});

test('unknown and old status links are not found', function () {
    $this->get('/r/not-a-real-code')->assertNotFound();

    $old = Reservation::factory()->for($this->shop)->for($this->item)->create([
        'board_date' => now('Asia/Kathmandu')->subDays(3)->toDateString(),
    ]);

    $this->get(route('reservation.show', $old->public_token))->assertNotFound();
});
