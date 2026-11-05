<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Didi Ko', 'Newa', 'Himalayan', 'Annapurna', 'Bhaktapur', 'Mustang'])
            .' '.fake()->randomElement(['Khaja Ghar', 'Momo Center', 'Thakali Kitchen', 'Bakery', 'Cafe']);

        return [
            'user_id' => User::factory()->afterCreating(
                fn (User $user) => $user->assignRole(UserRole::ShopOwner)
            ),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999999),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['momo', 'khaja-set', 'thakali', 'newari', 'bakery', 'tea-coffee', 'fast-food']),
            'area' => fake()->randomElement(['Baneshwor', 'Putalisadak', 'Thamel', 'Patan', 'Koteshwor', 'Kalanki']),
            'phone' => '98'.fake()->numerify('########'),
            'whatsapp_number' => null,
            'address' => fake()->streetAddress(),
            'timezone' => 'Asia/Kathmandu',
            'opens_at' => '07:00',
            'closes_at' => '20:00',
        ];
    }

    public function pro(): static
    {
        return $this->state(fn () => ['is_pro' => true]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['suspended_at' => now()]);
    }
}
