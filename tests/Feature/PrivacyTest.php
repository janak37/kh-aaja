<?php

use App\Console\Commands\PurgeReservationDetails;
use App\Enums\ReservationStatus;
use App\Logging\MaskPersonalData;
use App\Models\Reservation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Monolog\Level;
use Monolog\LogRecord;

beforeEach(function () {
    // 11:45 am in Kathmandu on 5 Nov.
    $this->travelTo(Carbon::parse('2026-11-05 06:00:00', 'UTC'));
});

function reservationFromDaysAgo(int $days): Reservation
{
    return Reservation::factory()->confirmed()->create([
        'board_date' => now('Asia/Kathmandu')->subDays($days)->toDateString(),
        'customer_name' => 'Sita',
        'customer_phone' => '9841234567',
        'note' => 'Extra achar please',
        'quantity' => 2,
    ]);
}

// Erasing old details

test('customer details are erased after 30 days, the order itself is kept', function () {
    $old = reservationFromDaysAgo(31);
    $edge = reservationFromDaysAgo(30);
    $recent = reservationFromDaysAgo(1);

    $this->artisan('reservations:purge')->assertSuccessful();

    $old->refresh();

    expect($old->customer_name)->toBeNull()
        ->and($old->customer_phone)->toBeNull()
        ->and($old->note)->toBeNull()
        ->and($old->purged_at)->not->toBeNull()
        ->and($old->quantity)->toBe(2)
        ->and($old->status)->toBe(ReservationStatus::Confirmed)
        ->and($edge->fresh()->customer_phone)->toBe('9841234567')
        ->and($recent->fresh()->customer_name)->toBe('Sita');
});

test('running the purge again changes nothing', function () {
    $old = reservationFromDaysAgo(40);

    $this->artisan('reservations:purge');
    $firstPurge = $old->fresh()->purged_at;

    $this->travel(1)->days();
    $this->artisan('reservations:purge');

    expect($old->fresh()->purged_at->equalTo($firstPurge))->toBeTrue();
});

test('the purge runs every night at 2:15 am Kathmandu time', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'reservations:purge'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('15 2 * * *')
        ->and($event->timezone)->toBe('Asia/Kathmandu');
});

// The Privacy page

test('the privacy page explains what happens to customer data', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Privacy')
        ->assertSee(PurgeReservationDetails::AFTER_DAYS.' days');
});

test('the privacy page is available in Nepali', function () {
    app()->setLocale('ne');

    Livewire::test('pages::privacy')->assertSee('गोपनीयता');
});

test('public pages link to the privacy page', function () {
    $this->get(route('home'))->assertSee(route('privacy'));
});

// Logs

test('phone numbers are hidden in log lines', function () {
    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Reservation failed for 9841234567',
        context: ['phone' => '+977 9812345678', 'nested' => ['text' => 'call 9701112223']],
    );

    $masked = (new MaskPersonalData)->mask($record);

    expect($masked->message)->toBe('Reservation failed for [phone]')
        ->and($masked->context['phone'])->toBe('[phone]')
        ->and($masked->context['nested']['text'])->toBe('call [phone]');
});

test('the log channels use the phone filter', function () {
    expect(config('logging.channels.stack.tap'))->toContain(MaskPersonalData::class)
        ->and(config('logging.channels.single.tap'))->toContain(MaskPersonalData::class)
        ->and(config('logging.channels.daily.tap'))->toContain(MaskPersonalData::class);
});
