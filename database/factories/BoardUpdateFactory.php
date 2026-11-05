<?php

namespace Database\Factories;

use App\Models\BoardUpdate;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardUpdate>
 */
class BoardUpdateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'board_date' => now('Asia/Kathmandu')->toDateString(),
            'last_updated_at' => now(),
        ];
    }
}
