<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
test('new users are given the shop owner role', function () {
    $this->post(route('register.store'), [
        'name' => 'Ram Shrestha',
        'email' => 'ram@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'ram@example.com')->firstOrFail();

    expect($user->hasRole(UserRole::ShopOwner))->toBeTrue()
        ->and($user->hasRole(UserRole::Admin))->toBeFalse();
});
