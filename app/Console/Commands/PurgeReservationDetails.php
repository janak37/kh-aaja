<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;

class PurgeReservationDetails extends Command
{
    /** Customer details are kept this many days after the reservation date. */
    public const AFTER_DAYS = 30;

    protected $signature = 'reservations:purge';

    protected $description = 'Erase customer names, phone numbers and notes 30 days after the reservation date';

    public function handle(): int
    {
        $cutoff = now('Asia/Kathmandu')->subDays(self::AFTER_DAYS)->toDateString();

        // Dish, quantity and status stay, because the shop's score needs them.
        // Nothing that identifies the customer is kept.
        $count = Reservation::query()
            ->whereNull('purged_at')
            ->where('board_date', '<', $cutoff)
            ->update([
                'customer_name' => null,
                'customer_phone' => null,
                'note' => null,
                'purged_at' => now(),
                'updated_at' => now(),
            ]);

        $this->info("Erased customer details from {$count} reservation(s).");

        return self::SUCCESS;
    }
}
