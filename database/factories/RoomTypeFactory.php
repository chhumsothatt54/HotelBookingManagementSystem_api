<?php
namespace Database\Factories;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomTypeFactory extends Factory {
    public function definition(): array {
        return [
            'hotel_id' => Hotel::factory(),
            'name' => fake()->randomElement(['Standard','Deluxe','Suite','Family']),
            'description' => fake()->sentence(),
            'max_guests' => fake()->numberBetween(1,6),
            'price_per_night' => fake()->numberBetween(20,300),
            'status' => 'active',
        ];
    }
}