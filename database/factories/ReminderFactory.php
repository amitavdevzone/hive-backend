<?php

namespace Database\Factories;

use App\Enums\ReminderType;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'notes' => fake()->optional()->sentence(),
            'type' => ReminderType::Once,
            'remind_at' => fake()->dateTimeBetween('now', '+1 month'),
            'interval_minutes' => null,
            'is_active' => true,
        ];
    }

    public function once(): static
    {
        return $this->state(fn () => [
            'type' => ReminderType::Once,
            'remind_at' => fake()->dateTimeBetween('now', '+1 month'),
            'interval_minutes' => null,
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn () => [
            'type' => ReminderType::Recurring,
            'remind_at' => null,
            'interval_minutes' => fake()->randomElement([30, 60, 120]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
