<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Mark reservations still waiting from an earlier day as expired';

    public function handle(): int
    {
        $count = Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->where('board_date', '<', now('Asia/Kathmandu')->toDateString())
            ->update([
                'status' => ReservationStatus::Expired->value,
                'updated_at' => now(),
            ]);

        $this->info("Expired {$count} reservation(s).");

        return self::SUCCESS;
    }
}
