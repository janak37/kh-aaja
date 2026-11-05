<?php

namespace Database\Factories;

use App\Enums\DeclineReason;
use App\Enums\ReservationStatus;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'item_id' => fn (array $attributes) => Item::factory()->create(['shop_id' => $attributes['shop_id']])->id,
            'board_date' => now('Asia/Kathmandu')->toDateString(),
            'customer_name' => fake()->name(),
            'customer_phone' => '98'.fake()->numerify('########'),
            'note' => null,
            'consent_at' => now(),
            'quantity' => fake()->numberBetween(1, 4),
            'status' => ReservationStatus::Pending,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => ReservationStatus::Confirmed, 'decided_at' => now()]);
    }

    public function declined(DeclineReason $reason = DeclineReason::SoldOut): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Declined,
            'decline_reason' => $reason,
            'decided_at' => now(),
        ]);
    }
}
