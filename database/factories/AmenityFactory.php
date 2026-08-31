<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;

class AmenityFactory extends Factory {
    public function definition(): array {
        return [
            'name' => fake()->randomElement(['WiFi','Pool','Parking','Breakfast','Gym','Air Conditioning','Restaurant','Spa','TV','Airport Shuttle']),
            'icon' => 'amenity-icon',
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
