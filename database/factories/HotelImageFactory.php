<?php
namespace Database\Factories;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

class HotelImageFactory extends Factory {
    public function definition(): array {
        return [
            'hotel_id' => Hotel::factory(),
            'image' => 'hotels/'.fake()->uuid().'.jpg',
            'is_primary' => fake()->boolean(20),
        ];
    }
}