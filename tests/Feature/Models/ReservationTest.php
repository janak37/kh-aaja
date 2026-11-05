<?php

use App\Enums\DeclineReason;
use App\Enums\ReservationStatus;
use App\Models\Reservation;

test('a public token is generated automatically', function () {
    $reservation = Reservation::factory()->create();

    expect($reservation->public_token)->toHaveLength(32);
});

test('every reservation gets a different token', function () {
    $tokens = Reservation::factory()->count(5)->create()->pluck('public_token');

    expect($tokens->unique())->toHaveCount(5);
});

test('the customer phone is hidden when converted to an array', function () {
    $reservation = Reservation::factory()->create();

    expect($reservation->toArray())->not->toHaveKey('customer_phone');
});

test('the reserved item belongs to the same shop', function () {
    $reservation = Reservation::factory()->create();

    expect($reservation->item->shop_id)->toBe($reservation->shop_id);
});

test('status and decline reason are cast to enums', function () {
    $reservation = Reservation::factory()->declined(DeclineReason::Closed)->create()->fresh();

    expect($reservation->status)->toBe(ReservationStatus::Declined)
        ->and($reservation->decline_reason)->toBe(DeclineReason::Closed);
});
