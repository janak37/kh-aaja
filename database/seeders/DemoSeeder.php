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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local-only demo data: 1 admin and 14 shops (one in every area), each with a
 * menu that fits its category, today's board, streak history and reservations.
 * Every login uses "password".
 *
 * Fixed logins:
 * - admin@example.com (admin)
 * - owner@example.com (Himalayan Momo Center, the best demo shop)
 */
class DemoSeeder extends Seeder
{
    /**
     * Menu per category: [name, price in Rs, veg, description].
     *
     * @var array<string, list<array{0: string, 1: int, 2: bool, 3: string}>>
     */
    private const MENUS = [
        'momo' => [
            ['Jhol Momo', 180, false, 'Steamed buff momo in a warm sesame and tomato soup.'],
            ['Buff Momo', 150, false, 'Ten steamed buffalo-meat dumplings with tomato achar.'],
            ['Veg Momo', 120, true, 'Cabbage, paneer and onion filling, steamed.'],
            ['Kothey Momo', 170, false, 'Half-fried momo, crisp on one side and soft on the other.'],
            ['Chicken Chowmein', 160, false, 'Stir-fried noodles with chicken and vegetables.'],
            ['Milk Tea', 30, true, 'Sweet milk tea, made fresh.'],
        ],
        'khaja-set' => [
            ['Veg Khaja Set', 200, true, 'Chiura with spiced potato, beans, greens and achar.'],
            ['Chicken Khaja Set', 280, false, 'Chiura with chicken curry, potato, achar and a boiled egg.'],
            ['Samosa', 30, true, 'Crisp pastry with spiced potato, served with chutney.'],
            ['Sel Roti', 25, true, 'Ring-shaped sweet rice bread, fried fresh.'],
            ['Jeri', 40, true, 'Syrupy fried swirls, best eaten warm.'],
            ['Milk Tea', 30, true, 'Sweet milk tea, made fresh.'],
        ],
        'thakali' => [
            ['Veg Thakali Set', 300, true, 'Rice, dal, vegetables, gundruk, achar and papad. Refills on rice and dal.'],
            ['Chicken Thakali Set', 380, false, 'The full Thakali set with home-style chicken curry.'],
            ['Mutton Thakali Set', 480, false, 'The full Thakali set with slow-cooked mutton.'],
            ['Sel Roti', 25, true, 'Ring-shaped sweet rice bread, fried fresh.'],
            ['Lassi', 90, true, 'Thick sweet yogurt drink.'],
            ['Milk Tea', 30, true, 'Sweet milk tea, made fresh.'],
        ],
        'newari' => [
            ['Samay Baji', 350, false, 'Beaten rice with choila, bara, egg, black soybeans, ginger and achar.'],
            ['Bara', 80, false, 'Black lentil pancake, plain or topped with egg.'],
            ['Chatamari', 150, false, 'Rice-flour crepe topped with minced meat and egg.'],
            ['Yomari', 60, true, 'Steamed rice-flour dumpling filled with chaku and sesame.'],
            ['Juju Dhau', 100, true, 'The king of curds from Bhaktapur, set in a clay bowl.'],
            ['Milk Tea', 30, true, 'Sweet milk tea, made fresh.'],
        ],
        'bakery' => [
            ['Bread Loaf', 120, true, 'Soft white loaf, baked this morning.'],
            ['Croissant', 90, true, 'Flaky butter croissant.'],
            ['Cream Roll', 50, true, 'Crisp pastry cone filled with fresh cream.'],
            ['Doughnut', 70, true, 'Glazed doughnut with sprinkles.'],
            ['Chocolate Cake', 150, true, 'A slice of chocolate cake with cream.'],
            ['Cookies', 100, true, 'Six chocolate-chip cookies.'],
        ],
        'tea-coffee' => [
            ['Milk Tea', 30, true, 'Sweet milk tea, made fresh.'],
            ['Masala Tea', 50, true, 'Milk tea with cardamom, cinnamon and clove.'],
            ['Lemon Tea', 40, true, 'Black tea with fresh lemon.'],
            ['Black Coffee', 120, true, 'Freshly brewed Nepali coffee.'],
            ['Cappuccino', 180, true, 'Espresso with steamed milk foam.'],
            ['Cookies', 100, true, 'Six chocolate-chip cookies.'],
        ],
        'fast-food' => [
            ['Chicken Burger', 280, false, 'Crispy chicken, lettuce and sauce in a sesame bun.'],
            ['French Fries', 150, true, 'Golden fries with ketchup.'],
            ['Pizza Slice', 180, false, 'Cheese and chicken sausage on a thin crust.'],
            ['Chicken Sandwich', 220, false, 'Grilled chicken, lettuce and mayo on toast.'],
            ['Hot Dog', 180, false, 'Chicken sausage with mustard in a soft bun.'],
            ['Lassi', 90, true, 'Thick sweet yogurt drink.'],
        ],
    ];

    /**
     * One shop per area: [name, category, area, opens, closes, owner, description].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>
     */
    private const SHOPS = [
        ['Himalayan Momo Center', 'momo', 'Thamel', '10:00', '21:00', 'Ramesh Shrestha', 'Steamed, jhol and kothey momo made by hand every morning since 2012.'],
        ['Stupa View Momo', 'momo', 'Boudha', '09:00', '20:00', 'Pemba Sherpa', 'Momo and chowmein with a view of the Boudha stupa.'],
        ['Didi Ko Khaja Ghar', 'khaja-set', 'Baneshwor', '07:00', '19:00', 'Sita Maharjan', 'Home-style khaja sets and fresh sel roti for office lunch breaks.'],
        ['Kalanki Khaja Pasal', 'khaja-set', 'Kalanki', '06:30', '20:00', 'Hari Thapa', 'Quick samosa, jeri and khaja sets right by the bus park.'],
        ['Mustang Thakali Kitchen', 'thakali', 'Lazimpat', '10:00', '21:30', 'Kamala Sherchan', 'Thakali dal bhat the Jomsom way, with refills on rice and dal.'],
        ['Jomsom Thakali Bhanchha', 'thakali', 'Koteshwor', '10:30', '21:00', 'Dinesh Tulachan', 'Family-run Thakali kitchen with slow-cooked mutton sets.'],
        ['Newa Lahana', 'newari', 'Patan', '11:00', '20:00', 'Rajesh Shakya', 'Samay baji, bara and chatamari near Patan Durbar Square.'],
        ['Bhaktapur Newari Kitchen', 'newari', 'Bhaktapur', '10:00', '19:30', 'Sunita Prajapati', 'Famous for juju dhau and fresh yomari.'],
        ['New Road Bakery Cafe', 'bakery', 'New Road', '07:00', '20:00', 'Bikash Tamrakar', 'Fresh bread, croissants and cakes, baked before 7 am.'],
        ['Didi Ko Bakery', 'bakery', 'Maharajgunj', '07:30', '20:00', 'Anita Karki', 'Small family bakery known for cream rolls and cookies.'],
        ['Chiya Chautari', 'tea-coffee', 'Chabahil', '06:00', '20:00', 'Suresh Adhikari', 'Masala tea, Nepali coffee and a quiet place to sit.'],
        ['Kirtipur Chiya Pasal', 'tea-coffee', 'Kirtipur', '06:30', '19:00', 'Gita Maharjan', 'Tea and cookies for students from the university.'],
        ['Balaju Burger House', 'fast-food', 'Balaju', '11:00', '22:00', 'Nabin Rai', 'Burgers, fries and hot dogs, made to order.'],
        ['Putalisadak Fast Food', 'fast-food', 'Putalisadak', '10:00', '21:00', 'Prakash Bhandari', 'Pizza slices and sandwiches near the college area.'],
    ];

    /** Shops that did not update today, so the homepage shows "Not updated today". */
    private const STALE = [11, 13];

    private const CUSTOMERS = ['Sita Rai', 'Bibek Shrestha', 'Asmita Gurung', 'Rohan Thapa'];

    public function run(): void
    {
        User::factory()
            ->create(['name' => 'Admin', 'email' => 'admin@example.com'])
            ->assignRole(UserRole::Admin);

        $today = now('Asia/Kathmandu');
        $photos = $this->copyPhotos();

        foreach (self::SHOPS as $i => [$name, $category, $area, $opens, $closes, $owner, $description]) {
            $shop = Shop::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
                'category' => $category,
                'area' => $area,
                'opens_at' => $opens,
                'closes_at' => $closes,
                'description' => $description,
            ]);
            $shop->owner->forceFill(['name' => $owner])->save();

            $items = collect(self::MENUS[$category])->values()->map(fn (array $dish, int $n) => Item::factory()->for($shop)->create([
                'name' => $dish[0],
                'default_price' => $dish[1],
                'is_veg' => $dish[2],
                'description' => $dish[3],
                'photo_path' => $photos[$dish[0]],
                'sort_order' => $n,
            ]));

            $updatedToday = ! in_array($i, self::STALE, true);
            $last = $items->count() - 1;

            if ($updatedToday) {
                foreach ($items as $n => $item) {
                    // The 2nd shop of each category stars different dishes (3rd and 4th),
                    // and each shop's board has its own update time, so specials come out mixed.
                    $first = ($i % 2) * 2;

                    DailyStatus::factory()->for($item)->create([
                        'board_date' => $today->toDateString(),
                        'status' => match ($n) {
                            $last - 1 => ItemStatus::Low,
                            $last => ItemStatus::SoldOut,
                            default => ItemStatus::Available,
                        },
                        'price' => null,
                        'is_special' => $n === $first || $n === $first + 1,
                        'special_note' => $n === $first ? 'Fresh batch till 2 pm' : null,
                        'updated_at' => now()->subMinutes(20 + $i * 9),
                    ]);
                }
            }

            // Streak history: 14, 13, 12 … days (never under 3); stale shops last updated 2-4 days ago.
            $days = $updatedToday ? range(0, max(3, 14 - $i) - 1) : [2, 3, 4];

            foreach ($days as $d) {
                BoardUpdate::factory()->for($shop)->create([
                    'board_date' => $today->subDays($d)->toDateString(),
                    // Stored in UTC like real updates: today = a few minutes ago, earlier days = 8:30 am Kathmandu.
                    'last_updated_at' => $d === 0
                        ? now()->subMinutes(20 + $i * 9)
                        : $today->subDays($d)->setTime(8, 30)->utc(),
                ]);
            }

            if ($updatedToday && $i < 8) {
                foreach ([0, 1] as $k) {
                    Reservation::factory()->for($shop)->for($items->firstOrFail())->create([
                        'board_date' => $today->toDateString(),
                        'customer_name' => self::CUSTOMERS[($i + $k) % count(self::CUSTOMERS)],
                        'customer_phone' => '98'.(41000000 + $i * 1000 + $k),
                        'quantity' => $k + 1,
                    ]);
                }
            }
        }

        // A fixed login for demos: owner@example.com / password.
        Shop::query()->where('slug', 'himalayan-momo-center')->firstOrFail()->owner
            ->forceFill(['email' => 'owner@example.com'])
            ->save();

        // Give every demo shop its Kh-aaja score, so the homepage ranking works straight away.
        Artisan::call('shops:score');
    }

    /**
     * Copies a photo for every demo dish to the public disk, the same place an
     * owner's upload goes, resized to 800 px wide WebP. A .jpg/.jpeg/.png photo
     * wins; a .webp is used only if no photo exists. Returns dish name => path.
     *
     * @return array<string, string>
     */
    private function copyPhotos(): array
    {
        $photos = [];

        foreach (self::MENUS as $menu) {
            foreach ($menu as [$name]) {
                if (isset($photos[$name])) {
                    continue;
                }

                $slug = Str::slug($name);
                $source = collect(['jpg', 'jpeg', 'png', 'webp'])
                    ->map(fn (string $ext) => database_path("seeders/images/{$slug}.{$ext}"))
                    ->first(fn (string $path) => is_file($path));

                if ($source === null) {
                    throw new RuntimeException("Missing demo photo for {$name}: add database/seeders/images/{$slug}.jpg");
                }

                $image = imagecreatefromstring((string) file_get_contents($source));

                if ($image === false) {
                    throw new RuntimeException("Cannot read {$source}");
                }

                $image = imagescale($image, min(800, imagesx($image))) ?: $image;

                ob_start();
                imagewebp($image, null, 75);
                Storage::disk('public')->put("dishes/demo/{$slug}.webp", (string) ob_get_clean());

                $photos[$name] = "dishes/demo/{$slug}.webp";
            }
        }

        return $photos;
    }
}
