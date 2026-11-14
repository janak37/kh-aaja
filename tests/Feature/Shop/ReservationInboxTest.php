<?php

use App\Enums\DeclineReason;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // 11:45 am in Kathmandu, so "today" can't change mid-test.
    $this->travelTo(Carbon::parse('2026-11-05 06:00:00', 'UTC'));

    $this->owner = User::factory()->create();
    $this->owner->assignRole(UserRole::ShopOwner);
    $this->shop = Shop::factory()->for($this->owner, 'owner')->create();
    $this->item = Item::factory()->for($this->shop)->create(['name' => 'Jhol Momo']);
});

function inbox(User $owner)
{
    return Livewire::actingAs($owner)->test('pages::shop.reservations');
}

/**
 * @param  array<string, mixed>  $attributes
 */
function newReservation(Shop $shop, Item $item, array $attributes = []): Reservation
{
    return Reservation::factory()->for($shop)->for($item)->create($attributes);
}

// The rules

test('a new reservation can be confirmed', function () {
    $r = newReservation($this->shop, $this->item);

    $r->confirm();

    expect($r->fresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($r->fresh()->decided_at)->not->toBeNull();
});

test('a confirmed reservation can be marked collected', function () {
    $r = Reservation::factory()->for($this->shop)->for($this->item)->confirmed()->create();

    $r->markCollected();

    expect($r->fresh()->status)->toBe(ReservationStatus::Collected);
});

test('a reservation can be declined with a reason', function () {
    $r = newReservation($this->shop, $this->item);

    $r->decline(DeclineReason::SoldOut);

    expect($r->fresh()->status)->toBe(ReservationStatus::Declined)
        ->and($r->fresh()->decline_reason)->toBe(DeclineReason::SoldOut);
});

test('steps cannot be skipped or undone', function () {
    $declined = Reservation::factory()->for($this->shop)->for($this->item)->declined()->create();
    $new = newReservation($this->shop, $this->item);

    expect(fn () => $declined->confirm())->toThrow(DomainException::class)
        ->and(fn () => $new->markCollected())->toThrow(DomainException::class);
});

// Auto-expire

test('only new reservations from earlier days expire', function () {
    $yesterday = now('Asia/Kathmandu')->subDay()->toDateString();

    $old = newReservation($this->shop, $this->item, ['board_date' => $yesterday]);
    $today = newReservation($this->shop, $this->item);
    $confirmedOld = Reservation::factory()->for($this->shop)->for($this->item)->confirmed()->create(['board_date' => $yesterday]);

    $this->artisan('reservations:expire')->assertSuccessful();

    expect($old->fresh()->status)->toBe(ReservationStatus::Expired)
        ->and($today->fresh()->status)->toBe(ReservationStatus::Pending)
        ->and($confirmedOld->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('expiry is scheduled every hour', function () {
    $scheduled = collect(app(Schedule::class)->events())->contains(
        fn ($event) => str_contains((string) $event->command, 'reservations:expire') && $event->expression === '0 * * * *'
    );

    expect($scheduled)->toBeTrue();
});

// The inbox page

test('guests are sent to the login page', function () {
    $this->get(route('shop.reservations'))->assertRedirect(route('login'));
});

test('the owner sees today\'s reservations with a tap-to-call phone', function () {
    newReservation($this->shop, $this->item, [
        'customer_name' => 'Sita',
        'customer_phone' => '9841234567',
        'quantity' => 2,
    ]);

    $this->actingAs($this->owner)
        ->get(route('shop.reservations'))
        ->assertOk()
        ->assertSee('2 × Jhol Momo')
        ->assertSee('Sita')
        ->assertSee('tel:9841234567', false);
});

test('other shops\' reservations are never shown', function () {
    Reservation::factory()->create(['customer_name' => 'Other Customer']);

    $this->actingAs($this->owner)
        ->get(route('shop.reservations'))
        ->assertDontSee('Other Customer');
});

test('the owner can confirm, collect and decline from the inbox', function () {
    $a = newReservation($this->shop, $this->item);
    $b = newReservation($this->shop, $this->item);

    inbox($this->owner)
        ->call('confirm', $a->id)
        ->call('collected', $a->id)
        ->call('decline', $b->id, 'closed');

    expect($a->fresh()->status)->toBe(ReservationStatus::Collected)
        ->and($b->fresh()->status)->toBe(ReservationStatus::Declined)
        ->and($b->fresh()->decline_reason)->toBe(DeclineReason::Closed);
});

test('the owner cannot act on another shop\'s reservation', function () {
    $other = Reservation::factory()->create();

    inbox($this->owner)->call('confirm', $other->id)->assertStatus(404);

    expect($other->fresh()->status)->toBe(ReservationStatus::Pending);
});

test('the dashboard shows how many reservations are waiting', function () {
    newReservation($this->shop, $this->item);
    newReservation($this->shop, $this->item);

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertSee('2 waiting')
        ->assertSee(route('shop.reservations'));
});
