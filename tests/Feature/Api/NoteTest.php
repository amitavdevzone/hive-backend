<?php

use App\Models\Note;
use App\Models\Tag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function validNotePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Server setup',
        'text' => 'Install nginx.',
        'format' => 'markdown',
        'tags' => ['server', 'office'],
    ], $overrides);
}

test('rejects guests', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/notes'],
    'store' => ['POST', '/api/notes'],
]);

test('creates a note with tags', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/notes', validNotePayload())
        ->assertCreated()
        ->assertJsonPath('title', 'Server setup')
        ->assertJsonPath('format', 'markdown')
        ->assertJsonCount(2, 'tags')
        ->assertJsonPath('tags.0.name', 'server');

    $note = $user->notes()->sole();
    expect($note->tags->pluck('name')->all())->toEqualCanonicalizing(['server', 'office']);
});

test('requires tags on create', function (array $overrides) {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/notes', validNotePayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tags');
})->with([
    'missing' => [['tags' => null]],
    'empty' => [['tags' => []]],
]);

test('validates input', function (array $overrides, string $field) {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/notes', validNotePayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing title' => [['title' => null], 'title'],
    'missing text' => [['text' => null], 'text'],
    'invalid format' => [['format' => 'html'], 'format'],
]);

test('reuses existing tags', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/notes', validNotePayload(['tags' => ['server']]))->assertCreated();
    $this->postJson('/api/notes', validNotePayload(['tags' => [' Server ']]))->assertCreated();

    expect($user->tags()->count())->toBe(1)
        ->and($user->notes()->count())->toBe(2);
});

test('does not reuse another user\'s tag', function () {
    $other = Tag::factory()->create(['name' => 'server']);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/notes', validNotePayload(['tags' => ['server']]))->assertCreated();

    expect($user->tags()->sole()->id)->not->toBe($other->id);
});

test('lists only own notes', function () {
    $user = User::factory()->create();
    $mine = Note::factory()->for($user)->create();
    $mine->tags()->attach(Tag::factory()->for($user)->create(['name' => 'office']));
    Note::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/notes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.format', $mine->format->value)
        ->assertJsonPath('data.0.tags.0.name', 'office')
        ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
});

test('paginates the list', function () {
    $user = User::factory()->create();
    Note::factory()->count(16)->for($user)->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/notes')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('total', 16);
});
