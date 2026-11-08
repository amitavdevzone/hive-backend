<?php

use App\Models\Tag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('rejects guests', function () {
    $this->getJson('/api/tags')->assertUnauthorized();
});

test('lists own tags', function () {
    $user = User::factory()->create();
    Tag::factory()->for($user)->create(['name' => 'server']);
    Tag::factory()->for($user)->create(['name' => 'office']);
    Tag::factory()->create(['name' => 'someone-elses']);
    Sanctum::actingAs($user);

    $this->getJson('/api/tags')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.name', 'office')
        ->assertJsonPath('1.name', 'server');
});
