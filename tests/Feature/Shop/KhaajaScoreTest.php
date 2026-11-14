<?php

use App\Enums\DeclineReason;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\BoardUpdate;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use App\Services\KhaajaScore;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // 11:45 am in Kathmandu on 5 Nov.
    $this->travelTo(Carbon::parse('2026-11-05 06:00:00', 'UTC'));
});

function updatedDaysAgo(Shop $shop, int ...$days): void
{
    foreach ($days as $d) {
        BoardUpdate::factory()->for($shop)->create([
            'board_date' => now('Asia/Kathmandu')->subDays($d)->toDateString(),
        ]);
    }
}

function scoreParts(Shop $shop): array
{
    return (new KhaajaScore)->parts($shop);
}

test('a brand-new shop starts near zero', function () {
    $parts = scoreParts(Shop::factory()->create());

    expect($parts['fresh'])->toBe(0)
        ->and($parts['consistent'])->toBe(0)
        ->and($parts['honest'])->toBe(0)
        ->and($parts['quick'])->toBe(KhaajaScore::QUICK_NEUTRAL)
        ->and($parts['loved'])->toBe(0);
});

test('freshness falls as the board gets older', function (int $daysAgo, int $points) {
    $shop = Shop::factory()->create();
    updatedDaysAgo($shop, $daysAgo);

    expect(scoreParts($shop)['fresh'])->toBe($points);
})->with([
    'today' => [0, 25],
    'yesterday' => [1, 18],
    '2 days ago' => [2, 12],
    '5 days ago' => [5, 5],
    '10 days ago' => [10, 0],
]);

test('updating every day for two weeks earns full consistency', function () {
    $half = Shop::factory()->create();
    $full = Shop::factory()->create();

    updatedDaysAgo($half, ...range(0, 6));
    updatedDaysAgo($full, ...range(0, 13));

    expect(scoreParts($half)['consistent'])->toBe(10)
        ->and(scoreParts($full)['consistent'])->toBe(20);
});

test('reservations declined as sold out cost honesty points', function () {
    $shop = Shop::factory()->create();
    updatedDaysAgo($shop, 0);

    Reservation::factory()->for($shop)->declined(DeclineReason::SoldOut)->count(2)->create();
    Reservation::factory()->for($shop)->declined(DeclineReason::Closed)->create(); // doesn't count

    expect(scoreParts($shop)['honest'])->toBe(5);
});

test('fast answers earn full quick-reply points', function () {
    $shop = Shop::factory()->create();

    Reservation::factory()->for($shop)->confirmed()->create([
        'created_at' => now()->subMinutes(5),
        'decided_at' => now(),
    ]);

    expect(scoreParts($shop)['quick'])->toBe(15);
});

test('unanswered reservations count as slow', function () {
    $shop = Shop::factory()->create();

    Reservation::factory()->for($shop)->create(['status' => ReservationStatus::Expired]);

    expect(scoreParts($shop)['quick'])->toBe(0);
});

test('hearts on the shop and its dishes add up', function () {
    $shop = Shop::factory()->create(['favourites_count' => 60]);
    Item::factory()->for($shop)->create(['favourites_count' => 39]);

    expect(scoreParts($shop)['loved'])->toBe(15);
});

test('the pro plan never changes the score', function () {
    $shop = Shop::factory()->create(['is_pro' => false]);
    updatedDaysAgo($shop, 0, 1, 2);
    $before = scoreParts($shop);

    $shop->forceFill(['is_pro' => true])->save();

    expect(scoreParts($shop->fresh()))->toBe($before);
});

test('the job saves the score without touching updated_at', function () {
    $shop = Shop::factory()->create();
    updatedDaysAgo($shop, 0);
    $updatedAt = $shop->fresh()->updated_at;

    $this->travel(1)->hours();
    $this->artisan('shops:score')->assertSuccessful();

    $fresh = $shop->fresh();
    $parts = json_decode((string) $fresh->getRawOriginal('score_parts'), true);

    expect($fresh->score)->toBeGreaterThan(0)
        ->and($parts['fresh'])->toBe(25)
        ->and($fresh->updated_at->equalTo($updatedAt))->toBeTrue();
});

test('the score job runs every hour', function () {
    $scheduled = collect(app(Schedule::class)->events())->contains(
        fn ($event) => str_contains((string) $event->command, 'shops:score') && $event->expression === '5 * * * *'
    );

    expect($scheduled)->toBeTrue();
});

test('among shops updated today, the higher score is shown first', function () {
    $low = Shop::factory()->create(['name' => 'Low Score Shop', 'score' => 30]);
    $high = Shop::factory()->create(['name' => 'High Score Shop', 'score' => 80]);
    updatedDaysAgo($low, 0);
    updatedDaysAgo($high, 0);

    $this->get(route('home'))->assertSeeInOrder(['High Score Shop', 'Low Score Shop']);
});

test('the owner sees the score and a tip on the dashboard', function () {
    $owner = User::factory()->create();
    $owner->assignRole(UserRole::ShopOwner);
    $shop = Shop::factory()->for($owner, 'owner')->create();
    updatedDaysAgo($shop, 0);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Kh-aaja score')
        ->assertSee('Fresh board')
        ->assertSee('💡', false);
});
