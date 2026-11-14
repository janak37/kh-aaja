<?php

use App\Enums\UserRole;
use App\Models\Item;
use App\Models\Shop;
use App\Models\User;

beforeEach(function () {
    $this->shop = Shop::factory()->create(['whatsapp_number' => '9841234567']);
    Item::factory()->for($this->shop)->create(['name' => 'Jhol Momo']);
});

test('pages are in English by default', function () {
    $this->get(route('board.show', $this->shop))
        ->assertSee('Call')
        ->assertDontSee('फोन गर्नुहोस्');
});

test('switching language saves a cookie and returns to the same page', function () {
    $board = route('board.show', $this->shop);

    $this->from($board)
        ->get(route('locale.switch', 'ne'))
        ->assertRedirect($board)
        ->assertCookie('locale', 'ne');
});

test('an unknown language is not found', function () {
    $this->get('/lang/xx')->assertNotFound();
});

test('switching never redirects to another website', function () {
    $this->from('https://evil.example.com/phish')
        ->get(route('locale.switch', 'ne'))
        ->assertRedirect(route('home'));
});

test('the public board is shown in Nepali when chosen', function () {
    $this->withCookie('locale', 'ne')
        ->get(route('board.show', $this->shop))
        ->assertSee('फोन गर्नुहोस्')
        ->assertSee('मेनु')
        ->assertSee('<html lang="ne"', false);
});

test('the owner Today page is shown in Nepali when chosen', function () {
    $owner = User::factory()->create();
    $owner->assignRole(UserRole::ShopOwner);
    $shop = Shop::factory()->for($owner, 'owner')->create();
    Item::factory()->for($shop)->create();

    $this->actingAs($owner)
        ->withCookie('locale', 'ne')
        ->get(route('shop.today'))
        ->assertSee('आज अझै अपडेट गरिएको छैन')
        ->assertSee('सकियो');
});

test('every nepali translation keeps the placeholders of its english text', function () {
    $translations = json_decode((string) file_get_contents(lang_path('ne.json')), true, flags: JSON_THROW_ON_ERROR);

    foreach ($translations as $english => $nepali) {
        expect($nepali)->not->toBe('', "Empty translation for: {$english}");

        preg_match_all('/:([a-z]+)/i', $english, $expected);
        preg_match_all('/:([a-z]+)/i', $nepali, $actual);

        expect(array_unique($actual[1]))->toEqualCanonicalizing(array_unique($expected[1]), "Placeholders differ in: {$english}")
            ->and(substr_count($nepali, '|'))->toBe(substr_count($english, '|'), "Plural parts differ in: {$english}");
    }
});
