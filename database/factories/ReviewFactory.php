<?php
namespace Database\Factories;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory {
    public function definition(): array {
        return [
            'booking_id' => Booking::factory(),
            'customer_id' => User::factory()->state(['role' => 'customer']),
            'hotel_id' => Hotel::factory(),
            'rating' => fake()->numberBetween(1,5),
            'comment' => fake()->sentence(),
            'status' => 'approved',
        ];
    }
}