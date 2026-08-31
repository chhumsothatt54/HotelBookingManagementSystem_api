<?php
namespace Database\Factories;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HotelFactory extends Factory {
    public function definition(): array {
        return [
            'manager_id' => User::factory()->state(['role' => 'hotel_manager']),
            'name' => fake()->company().' Hotel',
            'description' => fake()->paragraph(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'Cambodia',
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'phone' => fake()->numerify('0#########'),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'approved',
        ];
    }
}