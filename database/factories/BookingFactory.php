<?php
namespace Database\Factories;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingFactory extends Factory {
    public function definition(): array {
        $checkIn = fake()->dateTimeBetween('+1 day', '+30 days');
        $nights = fake()->numberBetween(1,7);
        $price = fake()->numberBetween(20,300);
        return [
            'booking_number' => 'BK-'.strtoupper(Str::random(10)),
            'customer_id' => User::factory()->state(['role' => 'customer']),
            'hotel_id' => Hotel::factory(),
            'room_id' => Room::factory(),
            'guest_name' => fake()->name(),
            'guest_phone' => fake()->numerify('0#########'),
            'guest_email' => fake()->safeEmail(),
            'check_in' => $checkIn,
            'check_out' => (clone $checkIn)->modify("+{$nights} days"),
            'total_guests' => fake()->numberBetween(1,4),
            'price_per_night' => $price,
            'nights' => $nights,
            'total_amount' => $price * $nights,
            'status' => 'confirmed',
            'cancel_reason' => null,
        ];
    }
}