<?php
namespace Database\Factories;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefundRequestFactory extends Factory {
    public function definition(): array {
        return [
            'booking_id' => Booking::factory(),
            'customer_id' => User::factory()->state(['role' => 'customer']),
            'amount' => fake()->numberBetween(10,300),
            'reason' => fake()->sentence(),
            'status' => 'pending',
            'processed_at' => null,
        ];
    }
}