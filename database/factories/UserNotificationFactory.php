<?php
namespace Database\Factories;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserNotificationFactory extends Factory {
    protected $model = \App\Models\UserNotification::class;
    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(10),
            'type' => fake()->randomElement(['booking','payment','system']),
            'is_read' => false,
        ];
    }
}