<?php
namespace Database\Factories;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PasswordResetOtpFactory extends Factory {
    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'otp' => fake()->numerify('######'),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
        ];
    }
}