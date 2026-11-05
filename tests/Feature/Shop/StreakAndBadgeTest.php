<?php

use App\Enums\DeclineReason;
use App\Enums\ItemStatus;
use App\Enums\UserRole;
use App\Models\BoardUpdate;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopBoard;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Freeze time at 11:45 am in Kathmandu so no test runs across midnight by accident.
    $this->travelTo(Carbon::parse('2026-11-05 06:00:00', 'UTC'));

    $this->owner = User::factory()->create();
    $this->owner->assignRole(UserRole::ShopOwner);
    $this->shop = Shop::factory()->for($this->owner, 'owner')->create();
    $this->board = new ShopBoard($this->shop);
});

/**
 * @param  array<int, int>  $daysAgo  e.g. [0, 1, 2] = today, yesterday, the day before
 */
function updatedOn(Shop $shop, array $daysAgo): void
{
    foreach ($daysAgo as $n) {
        BoardUpdate::factory()->for($shop)->create([
            'board_date' => now('Asia/Kathmandu')->subDays($n)->toDateString(),
        ]);
    }
}

// Streak

test('a shop that never updated has no streak', function () {
    expect($this->board->streak())->toBe(0);
});

test('updating today and the two days before gives a 3-day streak', function () {
    updatedOn($this->shop, [0, 1, 2]);

    expect($this->board->streak())->toBe(3);
});

test('the streak survives the morning before today\'s update', function () {
    updatedOn($this->shop, [1, 2]);

    expect($this->board->streak())->toBe(2);
});

test('a missed day resets the streak', function () {
    updatedOn($this->shop, [0, 1, 3, 4, 5]);

    expect($this->board->streak())->toBe(2);
});

test('a streak that ended two days ago is 0', function () {
    updatedOn($this->shop, [2, 3, 4]);

    expect($this->board->streak())->toBe(0);
});

test('the streak follows the shop clock at 23:59 and 00:01', function () {
    $item = Item::factory()->for($this->shop)->create();

    $this->travelTo(Carbon::parse('2026-11-05 18:14:00', 'UTC')); // 23:59 Nov 5 in Kathmandu
    $this->board->setStatus($item, ItemStatus::Available);
    expect($this->board->streak())->toBe(1);

    $this->travelTo(Carbon::parse('2026-11-05 18:16:00', 'UTC')); // 00:01 Nov 6 in Kathmandu
    expect($this->board->streak())->toBe(1);

    $this->board->setStatus($item, ItemStatus::Low);
    expect($this->board->streak())->toBe(2);
});

// Reliable board badge

test('10 updates in the last 14 days with no sold-out declines is reliable', function () {
    updatedOn($this->shop, range(0, 9));

    expect($this->board->isReliable())->toBeTrue();
});

test('9 updates in the last 14 days is not reliable yet', function () {
    updatedOn($this->shop, range(0, 8));

    expect($this->board->isReliable())->toBeFalse();
});

test('updates older than 14 days do not count', function () {
    updatedOn($this->shop, [0, 1, 2, 3, 4, 14, 15, 16, 17, 18]);

    expect($this->board->updatedDaysInWindow())->toBe(5)
        ->and($this->board->isReliable())->toBeFalse();
});

test('one sold-out decline keeps the badge', function () {
    updatedOn($this->shop, range(0, 9));
    Reservation::factory()->for($this->shop)->declined(DeclineReason::SoldOut)->create();

    expect($this->board->isReliable())->toBeTrue();
});

test('two sold-out declines lose the badge', function () {
    updatedOn($this->shop, range(0, 9));
    Reservation::factory()->for($this->shop)->declined(DeclineReason::SoldOut)->count(2)->create();

    expect($this->board->isReliable())->toBeFalse();
});

test('declines for other reasons do not count against the badge', function () {
    updatedOn($this->shop, range(0, 9));
    Reservation::factory()->for($this->shop)->declined(DeclineReason::Closed)->count(3)->create();

    expect($this->board->isReliable())->toBeTrue();
});

test('sold-out declines older than 14 days are forgotten', function () {
    updatedOn($this->shop, range(0, 9));
    Reservation::factory()->for($this->shop)->declined(DeclineReason::SoldOut)->count(2)->create([
        'board_date' => now('Asia/Kathmandu')->subDays(20)->toDateString(),
    ]);

    expect($this->board->isReliable())->toBeTrue();
});

// Where they show

test('the public board shows the reliable badge and the streak', function () {
    updatedOn($this->shop, range(0, 9));

    $this->get(route('board.show', $this->shop))
        ->assertSee('Reliable board')
        ->assertSee('10 days in a row');
});

test('the public board hides the badge for a shop that is not reliable', function () {
    updatedOn($this->shop, [0]);

    $this->get(route('board.show', $this->shop))
        ->assertDontSee('Reliable board')
        ->assertDontSee('days in a row');
});

test('the owner sees their streak on the Today page', function () {
    updatedOn($this->shop, [0, 1, 2, 3, 4]);

    $this->actingAs($this->owner)
        ->get(route('shop.today'))
        ->assertSee('5-day streak')
        ->assertSee('Updated 5 of the last 14 days');
});
