<?php
namespace Database\Factories;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory {
    public function definition(): array {
        return [
            'booking_id' => Booking::factory(),
            'amount' => fake()->numberBetween(20,500),
            'payment_method' => fake()->randomElement(['cash','aba','acleda','credit_card','other']),
            'transaction_id' => 'TXN-'.strtoupper(Str::random(12)),
            'status' => 'paid',
            'paid_at' => now(),
        ];
    }
}