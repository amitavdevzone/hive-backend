<?php

use App\Enums\ReminderType;
use App\Models\Reminder;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function validReminderPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Follow up with ABC',
        'type' => 'once',
        'remind_at' => '2026-12-01T14:00:00+05:30',
    ], $overrides);
}

test('rejects guests', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/reminders'],
    'store' => ['POST', '/api/reminders'],
]);

test('creates a one-time reminder', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/reminders', validReminderPayload())
        ->assertCreated()
        ->assertJsonPath('title', 'Follow up with ABC')
        ->assertJsonPath('type', 'once')
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('interval_minutes', null);

    $reminder = $user->reminders()->sole();
    expect($reminder->type)->toBe(ReminderType::Once)
        ->and($reminder->remind_at->toIso8601String())->toBe('2026-12-01T08:30:00+00:00')
        ->and($reminder->is_active)->toBeTrue();
});

test('creates a recurring reminder', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/reminders', ['title' => 'Drink water', 'type' => 'recurring', 'interval_minutes' => 30])
        ->assertCreated()
        ->assertJsonPath('type', 'recurring')
        ->assertJsonPath('interval_minutes', 30)
        ->assertJsonPath('remind_at', null);

    expect($user->reminders()->sole()->type)->toBe(ReminderType::Recurring);
});

test('requires the field for its type', function (array $payload, string $field) {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/reminders', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'once without remind_at' => [['title' => 'x', 'type' => 'once'], 'remind_at'],
    'recurring without interval' => [['title' => 'x', 'type' => 'recurring'], 'interval_minutes'],
]);

test('rejects fields of the other type', function (array $payload, string $field) {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/reminders', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'remind_at on recurring' => [['title' => 'x', 'type' => 'recurring', 'interval_minutes' => 30, 'remind_at' => '2026-12-01T14:00:00Z'], 'remind_at'],
    'interval on once' => [['title' => 'x', 'type' => 'once', 'remind_at' => '2026-12-01T14:00:00Z', 'interval_minutes' => 30], 'interval_minutes'],
]);

test('validates input', function (array $overrides, string $field) {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/reminders', validReminderPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing title' => [['title' => null], 'title'],
    'invalid type' => [['type' => 'weekly'], 'type'],
    'interval below 1' => [['type' => 'recurring', 'remind_at' => null, 'interval_minutes' => 0], 'interval_minutes'],
]);

test('lists only own reminders', function () {
    $user = User::factory()->create();
    $mine = Reminder::factory()->for($user)->count(2)->create();
    Reminder::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/reminders')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $mine->last()->id);
});

test('filters active reminders', function () {
    $user = User::factory()->create();
    $active = Reminder::factory()->for($user)->create();
    Reminder::factory()->for($user)->inactive()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/reminders?active=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);
});
