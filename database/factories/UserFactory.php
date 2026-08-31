<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory {
    public function definition(): array {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('0#########'),
            'password' => Hash::make('12345678'),
            'role' => 'customer',
            'avatar' => null,
            'email_verified_at' => now(),
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }
}