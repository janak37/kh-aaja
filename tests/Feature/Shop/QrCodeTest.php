<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Services\QrCode;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->assignRole(UserRole::ShopOwner);
    $this->shop = Shop::factory()->for($this->owner, 'owner')->create([
        'name' => 'Newa Momo Center',
        'phone' => '9841234567',
    ]);
});

test('guests are sent to the login page', function () {
    $this->get(route('shop.qr'))->assertRedirect(route('login'));
    $this->get(route('shop.qr.png'))->assertRedirect(route('login'));
    $this->get(route('shop.table-tent'))->assertRedirect(route('login'));
});

test('an owner without a shop is sent to set it up first', function () {
    $newOwner = User::factory()->create();
    $newOwner->assignRole(UserRole::ShopOwner);

    Livewire::actingAs($newOwner)->test('pages::shop.qr')->assertRedirect(route('shop.edit'));
});

test('the qr page shows the board link and a qr code', function () {
    $this->actingAs($this->owner)
        ->get(route('shop.qr'))
        ->assertOk()
        ->assertSee(route('board.show', $this->shop))
        ->assertSee('<svg', false);
});

test('the qr code downloads as a png named after the shop', function () {
    $response = $this->actingAs($this->owner)->get(route('shop.qr.png'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertDownload($this->shop->slug.'-qr.png');

    expect(substr((string) $response->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

test('the table card shows the shop, the qr code and nepali text', function () {
    $this->actingAs($this->owner)
        ->get(route('shop.table-tent'))
        ->assertOk()
        ->assertSee('Newa Momo Center')
        ->assertSee('आजको मेनु हेर्न स्क्यान गर्नुहोस्')
        ->assertSee('9841234567')
        ->assertSee('size: A5 portrait', false)
        ->assertSee('<svg', false);
});

test('admins cannot download a shop qr code', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin);

    $this->actingAs($admin)->get(route('shop.qr.png'))->assertForbidden();
});

test('the qr svg has no xml header so it can sit inside html', function () {
    expect(QrCode::svg('https://example.com'))->toStartWith('<svg');
});
