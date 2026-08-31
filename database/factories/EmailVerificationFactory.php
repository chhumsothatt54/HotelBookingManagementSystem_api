<?php
namespace Database\Factories;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EmailVerificationFactory extends Factory {
    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes(30),
            'verified_at' => null,
        ];
    }
}