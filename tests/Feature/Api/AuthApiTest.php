<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;

function apiOwner(): User
{
    $user = User::factory()->create(['email' => 'ramesh@example.com']);
    $user->assignRole(UserRole::ShopOwner);
    Shop::factory()->for($user, 'owner')->create(['name' => 'Himalayan Momo Center']);

    return $user;
}

test('a shop owner can register through the api and gets a token', function () {
    $this->postJson(route('api.v1.register'), [
        'name' => 'Sita Maharjan',
        'email' => 'sita@example.com',
        'password' => 'a-strong-password-123',
        'password_confirmation' => 'a-strong-password-123',
        'device_name' => 'Sita\'s Android',
    ])
        ->assertCreated()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'roles', 'shop']])
        ->assertJsonPath('user.roles', ['shop_owner']);

    expect(User::query()->where('email', 'sita@example.com')->exists())->toBeTrue();
});

test('register errors come back as json', function () {
    $this->postJson(route('api.v1.register'), ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name']);
});

test('an owner can log in with email and password', function () {
    apiOwner();

    $this->postJson(route('api.v1.login'), [
        'email' => 'ramesh@example.com',
        'password' => 'password',
        'device_name' => 'Postman',
    ])
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.shop.name', 'Himalayan Momo Center');
});

test('a wrong password gets no token', function () {
    apiOwner();

    $this->postJson(route('api.v1.login'), [
        'email' => 'ramesh@example.com',
        'password' => 'wrong-password',
        'device_name' => 'Postman',
    ])
        ->assertUnprocessable()
        ->assertJsonMissingPath('token');
});

test('me needs a token', function () {
    $this->getJson(route('api.v1.me'))->assertUnauthorized();
});

test('me returns the user and never the password', function () {
    $token = apiOwner()->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.v1.me'))
        ->assertOk()
        ->assertJsonPath('data.email', 'ramesh@example.com')
        ->assertJsonMissingPath('data.password');
});

test('logout revokes only the token that was used', function () {
    $user = apiOwner();
    $phone = $user->createToken('phone')->plainTextToken;
    $user->createToken('tablet');

    $this->withToken($phone)->postJson(route('api.v1.logout'))->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['tablet']);
});

test('login is limited to 6 attempts a minute', function () {
    apiOwner();

    foreach (range(1, 6) as $i) {
        $this->postJson(route('api.v1.login'), ['email' => 'ramesh@example.com', 'password' => 'wrong', 'device_name' => 'x']);
    }

    $this->postJson(route('api.v1.login'), ['email' => 'ramesh@example.com', 'password' => 'wrong', 'device_name' => 'x'])
        ->assertTooManyRequests();
});
