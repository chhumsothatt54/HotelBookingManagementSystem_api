<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Booking;
use App\Models\EmailVerification;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\PasswordResetOtp;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 15 Users: 1 admin, 4 managers, 10 customers
        $admin = User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $managers = User::factory(4)->create([
            'role' => 'hotel_manager',
            'status' => 'active',
        ]);

        $customers = User::factory(10)->create([
            'role' => 'customer',
            'status' => 'active',
        ]);

        // 15 email verifications
        $users = User::all();
        foreach ($users as $user) {
            EmailVerification::create([
                'user_id' => $user->id,
                'token' => Str::random(64),
                'expires_at' => now()->addMinutes(30),
                'verified_at' => now(),
            ]);
        }

        // 15 password reset OTPs
        foreach ($users as $user) {
            PasswordResetOtp::create([
                'user_id' => $user->id,
                'otp' => (string) random_int(100000, 999999),
                'expires_at' => now()->addMinutes(10),
                'verified_at' => null,
            ]);
        }

        // 15 Hotels
        $hotels = collect();
        for ($i = 0; $i < 15; $i++) {
            $hotels->push(Hotel::factory()->create([
                'manager_id' => $managers[$i % $managers->count()]->id,
            ]));
        }

        // 15 hotel images
        foreach ($hotels as $hotel) {
            HotelImage::factory()->create([
                'hotel_id' => $hotel->id,
                'is_primary' => true,
            ]);
        }

        // 15 room types
        $roomTypes = collect();
        for ($i = 0; $i < 15; $i++) {
            $roomTypes->push(RoomType::factory()->create([
                'hotel_id' => $hotels[$i]->id,
            ]));
        }

        // 15 rooms
        $rooms = collect();
        for ($i = 0; $i < 15; $i++) {
            $rooms->push(Room::factory()->create([
                'hotel_id' => $hotels[$i]->id,
                'room_type_id' => $roomTypes[$i]->id,
                'room_number' => str_pad((string) ($i + 101), 3, '0', STR_PAD_LEFT),
            ]));
        }

        // 15 amenities
        $amenityNames = [
            'WiFi','Pool','Parking','Breakfast','Gym',
            'Air Conditioning','Restaurant','Spa','TV','Airport Shuttle',
            'Laundry','Bar','Elevator','Room Service','Garden'
        ];

        $amenities = collect();
        foreach ($amenityNames as $name) {
            $amenities->push(Amenity::factory()->create([
                'name' => $name,
            ]));
        }

        // Room amenities
        foreach ($rooms as $index => $room) {
            $room->amenities()->sync([
                $amenities[$index % 15]->id,
                $amenities[($index + 1) % 15]->id,
            ]);
        }

        // 15 bookings
        $bookings = collect();
        for ($i = 0; $i < 15; $i++) {
            $checkIn = now()->addDays($i + 1);
            $nights = random_int(1, 5);
            $price = $roomTypes[$i]->price_per_night;

            $bookings->push(Booking::create([
                'booking_number' => 'BK-'.strtoupper(Str::random(10)),
                'customer_id' => $customers[$i % $customers->count()]->id,
                'hotel_id' => $hotels[$i]->id,
                'room_id' => $rooms[$i]->id,
                'guest_name' => $customers[$i % $customers->count()]->name,
                'guest_phone' => $customers[$i % $customers->count()]->phone,
                'guest_email' => $customers[$i % $customers->count()]->email,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkIn->copy()->addDays($nights)->toDateString(),
                'total_guests' => random_int(1, 4),
                'price_per_night' => $price,
                'nights' => $nights,
                'total_amount' => $price * $nights,
                'status' => 'confirmed',
            ]));
        }

        // 15 payments
        foreach ($bookings as $booking) {
            Payment::factory()->create([
                'booking_id' => $booking->id,
                'amount' => $booking->total_amount,
            ]);
        }

        // 15 refund requests
        foreach ($bookings as $booking) {
            RefundRequest::factory()->create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'amount' => $booking->total_amount,
            ]);
        }

        // 15 reviews
        foreach ($bookings as $booking) {
            Review::factory()->create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'hotel_id' => $booking->hotel_id,
            ]);
        }

        // 15 notifications
        for ($i = 0; $i < 15; $i++) {
            UserNotification::factory()->create([
                'user_id' => $users[$i]->id,
            ]);
        }
    }
}
