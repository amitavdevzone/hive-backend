<?php

namespace Database\Seeders;

use App\Models\Note;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class NoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        $tags = collect(['office', 'project', 'personal', 'server'])
            ->map(fn (string $name) => Tag::query()->firstOrCreate(['user_id' => $user->id, 'name' => $name]));

        Note::factory(10)
            ->for($user)
            ->create()
            ->each(fn (Note $note) => $note->tags()->attach($tags->random(rand(1, 3))->pluck('id')));
    }
}
