<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->assignRole(UserRole::ShopOwner);
});

function fillShopForm($component)
{
    return $component
        ->set('name', 'Didi Ko Khaja Ghar')
        ->set('category', 'momo')
        ->set('area', 'Baneshwor')
        ->set('phone', '9841234567');
}

test('guests are sent to the login page', function () {
    $this->get(route('shop.edit'))->assertRedirect(route('login'));
});

test('admins cannot open the shop page', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin);

    $this->actingAs($admin)->get(route('shop.edit'))->assertForbidden();
});

test('a shop owner can open the shop page', function () {
    $this->actingAs($this->owner)->get(route('shop.edit'))->assertOk();
});

test('a shop owner can create their shop', function () {
    fillShopForm(Livewire::actingAs($this->owner)->test('pages::shop.profile'))
        ->call('save')
        ->assertHasNoErrors();

    $shop = $this->owner->shop()->first();

    expect($shop->name)->toBe('Didi Ko Khaja Ghar')
        ->and($shop->slug)->toBe('didi-ko-khaja-ghar')
        ->and($shop->timezone)->toBe('Asia/Kathmandu');
});

test('a taken slug gets a number added', function () {
    Shop::factory()->create(['slug' => 'didi-ko-khaja-ghar']);

    fillShopForm(Livewire::actingAs($this->owner)->test('pages::shop.profile'))->call('save');

    expect($this->owner->shop()->first()->slug)->toBe('didi-ko-khaja-ghar-2');
});

test('renaming a shop keeps its slug', function () {
    $shop = Shop::factory()->for($this->owner, 'owner')->create(['slug' => 'old-name']);

    Livewire::actingAs($this->owner)
        ->test('pages::shop.profile')
        ->set('name', 'Brand New Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($shop->fresh()->name)->toBe('Brand New Name')
        ->and($shop->fresh()->slug)->toBe('old-name');
});

test('saving never touches another owner\'s shop', function () {
    $otherShop = Shop::factory()->create(['name' => 'Other Shop']);

    fillShopForm(Livewire::actingAs($this->owner)->test('pages::shop.profile'))->call('save');

    expect($otherShop->fresh()->name)->toBe('Other Shop')
        ->and(Shop::count())->toBe(2);
});

test('invalid phone, category and area are rejected', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::shop.profile')
        ->set('name', 'Test')
        ->set('category', 'pizza-planet')
        ->set('area', 'Mars')
        ->set('phone', '12345')
        ->call('save')
        ->assertHasErrors(['category', 'area', 'phone']);

    expect($this->owner->shop()->exists())->toBeFalse();
});

test('the sidebar shows My shop only to shop owners', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertSee(route('shop.edit'));

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertDontSee(route('shop.edit'));
});
