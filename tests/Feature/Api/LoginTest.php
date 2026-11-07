<?php

use App\Models\User;

test('returns a sanctum token for valid credentials', function () {
    $user = User::factory()->create();

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonStructure(['access_token', 'token_type'])
        ->assertJson(['token_type' => 'Bearer']);

    expect($user->tokens()->count())->toBe(1);
});

test('rejects unknown email', function () {
    $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'These credentials are incorrect.']);
});

test('rejects wrong password with the same message', function () {
    $user = User::factory()->create();

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'These credentials are incorrect.']);
});

test('rejects unverified user', function () {
    $user = User::factory()->unverified()->create();

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'These credentials are incorrect.']);

    expect($user->tokens()->count())->toBe(0);
});

test('validates input', function (array $payload, string $field) {
    $this->postJson('/api/login', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing email' => [['password' => 'password'], 'email'],
    'malformed email' => [['email' => 'not-an-email', 'password' => 'password'], 'email'],
    'missing password' => [['email' => 'a@example.com'], 'password'],
]);

test('throttles login attempts', function () {
    foreach (range(1, 6) as $attempt) {
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'x']);
    }

    $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'x'])
        ->assertTooManyRequests();
});
