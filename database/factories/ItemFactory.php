<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->randomElement([
                'Jhol Momo', 'Buff Momo', 'Chicken Chowmein', 'Veg Khaja Set', 'Thakali Set',
                'Sel Roti', 'Samosa', 'Milk Tea', 'Aloo Paratha', 'Chatamari',
            ]),
            'description' => fake()->optional()->sentence(6),
            'default_price' => fake()->randomElement([40, 60, 120, 150, 180, 220, 350]),
            'is_veg' => fake()->boolean(40),
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['is_archived' => true]);
    }
}
