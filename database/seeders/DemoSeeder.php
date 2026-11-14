<?php

namespace Database\Seeders;

use App\Enums\ItemStatus;
use App\Enums\UserRole;
use App\Models\BoardUpdate;
use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Local-only demo data: 1 admin, 6 shops with menus, today's boards,
 * streak history and a few reservations. Every login uses "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()
            ->create(['name' => 'Admin', 'email' => 'admin@example.com'])
            ->assignRole(UserRole::Admin);

        $today = now('Asia/Kathmandu');
        $dishes = ['Jhol Momo', 'Buff Momo', 'Chicken Chowmein', 'Veg Khaja Set', 'Thakali Set', 'Sel Roti', 'Samosa', 'Milk Tea'];

        Shop::factory()->count(6)->create()->each(function (Shop $shop, int $i) use ($today, $dishes): void {
            $items = Item::factory()
                ->for($shop)
                ->count(count($dishes))
                ->sequence(fn (Sequence $s) => ['name' => $dishes[$s->index], 'sort_order' => $s->index])
                ->create();

            // Shops 0-4 updated today; shop 5 has an old board ("not updated today")
            $updatedToday = $i < 5;

            if ($updatedToday) {
                foreach ($items as $n => $item) {
                    DailyStatus::factory()->for($item)->create([
                        'board_date' => $today->toDateString(),
                        'status' => match ($n) {
                            5 => ItemStatus::Low,
                            6 => ItemStatus::SoldOut,
                            default => ItemStatus::Available,
                        },
                        'is_special' => $n < 2,
                        'special_note' => $n === 0 ? 'Fresh batch till 2 pm' : null,
                    ]);
                }
            }

            // Streak history: 14, 12, 10, 8, 6 days, and 3 old days for the stale shop
            $firstDay = $updatedToday ? 0 : 1;
            $lastDay = $updatedToday ? 14 - $i * 2 : 4;

            for ($d = $firstDay; $d < $lastDay; $d++) {
                BoardUpdate::factory()->for($shop)->create([
                    'board_date' => $today->copy()->subDays($d)->toDateString(),
                    'last_updated_at' => $today->copy()->subDays($d)->setTime(8, 30),
                ]);
            }

            Reservation::factory()
                ->for($shop)
                ->for($items->firstOrFail())
                ->count(2)
                ->create(['board_date' => $today->toDateString()]);
        });

        // Give every demo shop its Kh-aaja score, so the homepage ranking works straight away.
        Artisan::call('shops:score');
    }
}
