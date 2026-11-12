<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Admin']);
    $this->admin->assignRole(UserRole::Admin);
});

function adminPage(User $admin)
{
    return Livewire::actingAs($admin)->test('pages::admin.shops');
}

test('guests are sent to the login page', function () {
    $this->get(route('admin.shops'))->assertRedirect(route('login'));
});

test('shop owners cannot open the admin page', function () {
    $owner = User::factory()->create();
    $owner->assignRole(UserRole::ShopOwner);

    $this->actingAs($owner)->get(route('admin.shops'))->assertForbidden();
});

test('the admin sees every shop with its owner', function () {
    $shop = Shop::factory()->create(['name' => 'Newa Momo Center']);

    $this->actingAs($this->admin)
        ->get(route('admin.shops'))
        ->assertOk()
        ->assertSee('Newa Momo Center')
        ->assertSee($shop->owner->email);
});

test('the admin can search shops', function () {
    Shop::factory()->create(['name' => 'Newa Momo Center']);
    Shop::factory()->create(['name' => 'Thakali Kitchen']);

    adminPage($this->admin)
        ->set('search', 'Newa')
        ->assertSee('Newa Momo Center')
        ->assertDontSee('Thakali Kitchen');
});

test('the suspended filter shows only suspended shops', function () {
    Shop::factory()->create(['name' => 'Open Shop']);
    Shop::factory()->suspended()->create(['name' => 'Hidden Shop']);

    adminPage($this->admin)
        ->set('filter', 'suspended')
        ->assertSee('Hidden Shop')
        ->assertDontSee('Open Shop');
});

test('the admin can make a shop pro and remove it again', function () {
    $shop = Shop::factory()->create();

    adminPage($this->admin)->call('togglePro', $shop->id);
    expect($shop->fresh()->is_pro)->toBeTrue();

    adminPage($this->admin)->call('togglePro', $shop->id);
    expect($shop->fresh()->is_pro)->toBeFalse();
});

test('suspending a shop hides its public board until restored', function () {
    $shop = Shop::factory()->create();

    adminPage($this->admin)->call('toggleSuspended', $shop->id);
    expect($shop->fresh()->isSuspended())->toBeTrue();
    $this->get(route('board.show', $shop))->assertNotFound();

    adminPage($this->admin)->call('toggleSuspended', $shop->id);
    expect($shop->fresh()->isSuspended())->toBeFalse();
    $this->get(route('board.show', $shop))->assertOk();
});

test('the admin dashboard links to the shops page', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertSee('Manage shops')
        ->assertSee(route('admin.shops'));
});

test('the owner of a suspended shop sees a notice', function () {
    $owner = User::factory()->create();
    $owner->assignRole(UserRole::ShopOwner);
    Shop::factory()->for($owner, 'owner')->suspended()->create();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertSee('Your shop is suspended');
});
