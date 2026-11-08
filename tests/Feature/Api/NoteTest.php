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
    'show' => ['GET', '/api/notes/1'],
    'update' => ['PUT', '/api/notes/1'],
    'destroy' => ['DELETE', '/api/notes/1'],
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

test('searches title and text case-insensitively', function () {
    $user = User::factory()->create();
    $inTitle = Note::factory()->for($user)->create(['title' => 'Nginx config', 'text' => 'x']);
    $inText = Note::factory()->for($user)->create(['title' => 'y', 'text' => 'Restart NGINX daily']);
    Note::factory()->for($user)->create(['title' => 'Other', 'text' => 'nothing']);
    Sanctum::actingAs($user);

    $ids = collect($this->getJson('/api/notes?search=nGiNx')->assertOk()->json('data'))->pluck('id');

    expect($ids->all())->toEqualCanonicalizing([$inTitle->id, $inText->id]);
});

test('treats like wildcards in search literally', function () {
    $user = User::factory()->create();
    $literal = Note::factory()->for($user)->create(['title' => '100% done', 'text' => 'x']);
    Note::factory()->for($user)->create(['title' => '100 done', 'text' => 'x']);
    Sanctum::actingAs($user);

    $this->getJson('/api/notes?search='.urlencode('100%'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $literal->id);
});

test('filters by tag', function () {
    $user = User::factory()->create();
    $tagged = Note::factory()->for($user)->create();
    $tagged->tags()->attach(Tag::factory()->for($user)->create(['name' => 'server']));
    Note::factory()->for($user)->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/notes?tag=Server')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $tagged->id);
});

test('combines search and tag without leaking other users', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'server']);
    $match = Note::factory()->for($user)->create(['title' => 'nginx']);
    Note::factory()->for($user)->create(['title' => 'nginx']);
    $wrongSearch = Note::factory()->for($user)->create(['title' => 'apache']);
    $match->tags()->attach($tag);
    $wrongSearch->tags()->attach($tag);

    $other = User::factory()->create();
    Note::factory()->for($other)->create(['title' => 'nginx'])
        ->tags()->attach(Tag::factory()->for($other)->create(['name' => 'server']));
    Sanctum::actingAs($user);

    $this->getJson('/api/notes?search=nginx&tag=server')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);

    $this->getJson('/api/notes?search=nginx')->assertJsonCount(2, 'data');
});

test('shows own note and forbids others', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();
    $note->tags()->attach(Tag::factory()->for($user)->create(['name' => 'office']));

    Sanctum::actingAs($user);
    $this->getJson("/api/notes/{$note->id}")
        ->assertOk()
        ->assertJsonPath('id', $note->id)
        ->assertJsonPath('tags.0.name', 'office');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/notes/{$note->id}")->assertForbidden();
});

test('updates a note and replaces its tags', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create(['format' => 'plain']);
    $note->tags()->attach(Tag::factory()->for($user)->create(['name' => 'old']));
    Sanctum::actingAs($user);

    $this->putJson("/api/notes/{$note->id}", validNotePayload(['title' => 'New title', 'tags' => ['fresh']]))
        ->assertOk()
        ->assertJsonPath('title', 'New title')
        ->assertJsonPath('format', 'markdown')
        ->assertJsonCount(1, 'tags')
        ->assertJsonPath('tags.0.name', 'fresh');

    expect($note->fresh()->tags->pluck('name')->all())->toBe(['fresh']);
});

test('forbids updating another user\'s note', function () {
    $note = Note::factory()->create(['title' => 'Original']);
    Sanctum::actingAs(User::factory()->create());

    $this->putJson("/api/notes/{$note->id}", validNotePayload())->assertForbidden();

    expect($note->fresh()->title)->toBe('Original');
});

test('requires tags on update', function (array $overrides) {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();
    Sanctum::actingAs($user);

    $this->putJson("/api/notes/{$note->id}", validNotePayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tags');
})->with([
    'missing' => [['tags' => null]],
    'empty' => [['tags' => []]],
]);

test('deletes own note and forbids others', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    Sanctum::actingAs(User::factory()->create());
    $this->deleteJson("/api/notes/{$note->id}")->assertForbidden();
    expect(Note::query()->whereKey($note->id)->exists())->toBeTrue();

    Sanctum::actingAs($user);
    $this->deleteJson("/api/notes/{$note->id}")->assertNoContent();
    expect(Note::query()->whereKey($note->id)->exists())->toBeFalse();
});
