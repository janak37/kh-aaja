<?php

namespace Database\Factories;

use App\Enums\ItemStatus;
use App\Models\DailyStatus;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyStatus>
 */
class DailyStatusFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'board_date' => now('Asia/Kathmandu')->toDateString(),
            'status' => ItemStatus::Available,
            'price' => null,
            'is_special' => false,
            'special_note' => null,
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn () => ['status' => ItemStatus::SoldOut]);
    }

    public function special(?string $note = null): static
    {
        return $this->state(fn () => ['is_special' => true, 'special_note' => $note]);
    }
}
