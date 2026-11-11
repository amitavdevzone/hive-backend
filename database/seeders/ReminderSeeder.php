<?php

namespace Database\Seeders;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReminderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        Reminder::factory()->for($user)->recurring()->create(['title' => 'Drink water', 'interval_minutes' => 30]);
        Reminder::factory()->for($user)->recurring()->create(['title' => 'Walk', 'interval_minutes' => 60]);
        Reminder::factory()->for($user)->once()->create(['title' => 'Follow up with ABC']);
        Reminder::factory()->for($user)->once()->inactive()->create();
    }
}
