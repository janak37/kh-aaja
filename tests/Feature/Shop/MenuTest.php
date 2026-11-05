<?php

use App\Enums\UserRole;
use App\Models\Item;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->assignRole(UserRole::ShopOwner);
    $this->shop = Shop::factory()->for($this->owner, 'owner')->create();
});

function menuPage(User $owner)
{
    return Livewire::actingAs($owner)->test('pages::shop.menu');
}

test('guests are sent to the login page', function () {
    $this->get(route('shop.menu'))->assertRedirect(route('login'));
});

test('an owner without a shop is sent to set it up first', function () {
    $newOwner = User::factory()->create();
    $newOwner->assignRole(UserRole::ShopOwner);

    menuPage($newOwner)->assertRedirect(route('shop.edit'));
});

test('an owner can add a dish', function () {
    menuPage($this->owner)
        ->set('name', 'Jhol Momo')
        ->set('default_price', '180')
        ->call('save')
        ->assertHasNoErrors();

    $item = $this->shop->items()->first();

    expect($item->name)->toBe('Jhol Momo')
        ->and($item->default_price)->toBe(180)
        ->and($item->dish_key)->toBe('jhol-momo')
        ->and($item->photo_path)->toBeNull();
});

test('new dishes go to the end of the menu', function () {
    Item::factory()->for($this->shop)->create(['sort_order' => 5]);

    menuPage($this->owner)->set('name', 'Samosa')->call('save');

    expect($this->shop->items()->where('name', 'Samosa')->first()->sort_order)->toBe(6);
});

test('an owner can edit a dish', function () {
    $item = Item::factory()->for($this->shop)->create(['name' => 'Jhol Momo']);

    menuPage($this->owner)
        ->call('edit', $item->id)
        ->assertSet('name', 'Jhol Momo')
        ->set('name', 'Buff Jhol Momo')
        ->call('save')
        ->assertHasNoErrors();

    expect($item->fresh()->name)->toBe('Buff Jhol Momo');
});

test('the same dish name cannot be added twice to one shop', function () {
    Item::factory()->for($this->shop)->create(['name' => 'Samosa']);

    menuPage($this->owner)
        ->set('name', 'Samosa')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('another shop can use the same dish name', function () {
    Item::factory()->create(['name' => 'Samosa']);

    menuPage($this->owner)
        ->set('name', 'Samosa')
        ->call('save')
        ->assertHasNoErrors();
});

test('an owner can archive and restore a dish', function () {
    $item = Item::factory()->for($this->shop)->create();

    menuPage($this->owner)->call('archive', $item->id);
    expect($item->fresh()->is_archived)->toBeTrue();

    menuPage($this->owner)->call('restore', $item->id);
    expect($item->fresh()->is_archived)->toBeFalse();
});

test('an owner can move a dish up', function () {
    Item::factory()->for($this->shop)->create(['name' => 'A', 'sort_order' => 0]);
    Item::factory()->for($this->shop)->create(['name' => 'B', 'sort_order' => 1]);
    $c = Item::factory()->for($this->shop)->create(['name' => 'C', 'sort_order' => 2]);

    menuPage($this->owner)->call('move', $c->id, -1);

    expect($this->shop->items()->pluck('name')->all())->toBe(['A', 'C', 'B']);
});

test('an owner cannot edit another shop\'s dish', function () {
    $otherItem = Item::factory()->create();

    menuPage($this->owner)->call('edit', $otherItem->id)->assertStatus(404);
});

test('an owner cannot archive another shop\'s dish', function () {
    $otherItem = Item::factory()->create();

    menuPage($this->owner)->call('archive', $otherItem->id)->assertStatus(404);

    expect($otherItem->fresh()->is_archived)->toBeFalse();
});

// Photos

test('a dish photo is resized to 800px wide and saved as webp', function () {
    Storage::fake('public');

    menuPage($this->owner)
        ->set('name', 'Jhol Momo')
        ->set('photo', UploadedFile::fake()->image('momo.jpg', 1600, 1200))
        ->call('save')
        ->assertHasNoErrors();

    $path = $this->shop->items()->first()->photo_path;

    expect($path)->toStartWith('dishes/'.$this->shop->id.'/')->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);

    $image = imagecreatefromstring((string) Storage::disk('public')->get($path));
    expect(imagesx($image))->toBe(800)
        ->and(imagesy($image))->toBe(600);
});

test('a small photo is not stretched', function () {
    Storage::fake('public');

    menuPage($this->owner)
        ->set('name', 'Samosa')
        ->set('photo', UploadedFile::fake()->image('samosa.png', 400, 300))
        ->call('save');

    $path = $this->shop->items()->first()->photo_path;
    $image = imagecreatefromstring((string) Storage::disk('public')->get($path));

    expect(imagesx($image))->toBe(400);
});

test('replacing a photo deletes the old file', function () {
    Storage::fake('public');
    Storage::disk('public')->put('dishes/old.webp', 'old');
    $item = Item::factory()->for($this->shop)->create(['photo_path' => 'dishes/old.webp']);

    menuPage($this->owner)
        ->call('edit', $item->id)
        ->set('photo', UploadedFile::fake()->image('new.jpg', 1000, 800))
        ->call('save')
        ->assertHasNoErrors();

    Storage::disk('public')->assertMissing('dishes/old.webp');
    expect($item->fresh()->photo_path)->not->toBe('dishes/old.webp');
});

test('an owner can remove a photo', function () {
    Storage::fake('public');
    Storage::disk('public')->put('dishes/old.webp', 'old');
    $item = Item::factory()->for($this->shop)->create(['photo_path' => 'dishes/old.webp']);

    menuPage($this->owner)
        ->call('edit', $item->id)
        ->call('removePhoto');

    Storage::disk('public')->assertMissing('dishes/old.webp');
    expect($item->fresh()->photo_path)->toBeNull();
});

test('a file that is not an image is rejected', function () {
    Storage::fake('public');

    menuPage($this->owner)
        ->set('name', 'Samosa')
        ->set('photo', UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['photo']);

    expect($this->shop->items()->count())->toBe(0);
});
