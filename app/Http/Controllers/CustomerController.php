<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Hotels & Room Viewing (Public Features)
    |--------------------------------------------------------------------------
    */

    /**
     * Get all approved hotels with search & filter
     */
    public function hotels(Request $request)
    {
        $query = Hotel::where('status', 'approved')
            ->with(['roomTypes', 'amenities']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        $hotels = $query->latest()->paginate(12);

        return response()->json($hotels);
    }

    /**
     * Get hotel detail
     */
    public function hotelDetail($id)
    {
        $hotel = Hotel::where('status', 'approved')
            ->with([
                'roomTypes',
                'rooms',
                'amenities',
                'reviews.user'
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $hotel
        ]);
    }

    /**
     * Get room types for a hotel
     */
    public function roomTypes($hotelId)
    {
        $hotel = Hotel::where('status', 'approved')->findOrFail($hotelId);

        $roomTypes = RoomType::where('hotel_id', $hotel->id)
            ->with('rooms')
            ->get();

        return response()->json([
            'hotel' => $hotel->name,
            'data' => $roomTypes
        ]);
    }

    /**
     * Get available rooms (Check real availability by date if provided)
     */
    public function availableRooms(Request $request, $hotelId)
    {
        $hotel = Hotel::where('status', 'approved')->findOrFail($hotelId);

        $query = Room::where('hotel_id', $hotel->id)
            ->where('status', 'available')
            ->with('roomType');

        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        // Filter out rooms booked within the selected date range
        if ($request->filled(['check_in', 'check_out'])) {
            $checkIn = $request->check_in;
            $checkOut = $request->check_out;

            $query->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->whereIn('status', ['pending', 'approved', 'confirmed'])
                  ->where('check_in', '<', $checkOut)
                  ->where('check_out', '>', $checkIn);
            });
        }

        $rooms = $query->get();

        return response()->json([
            'hotel' => $hotel->name,
            'data' => $rooms
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Bookings Management (Protected Features)
    |--------------------------------------------------------------------------
    */

    /**
     * Create booking
     */
    public function createBooking(Request $request)
    {
        $validated = $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'room_type_id' => 'required|exists:room_types,id',
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guests' => 'required|integer|min:1',
        ]);

        $hotel = Hotel::where('id', $validated['hotel_id'])
            ->where('status', 'approved')
            ->first();

        if (!$hotel) {
            return response()->json(['message' => 'Hotel is not available.'], 404);
        }

        $room = Room::where('id', $validated['room_id'])
            ->where('hotel_id', $hotel->id)
            ->where('room_type_id', $validated['room_type_id'])
            ->first();

        if (!$room || $room->status !== 'available') {
            return response()->json(['message' => 'Invalid or unavailable room.'], 422);
        }

        // Check for double booking conflict
        $alreadyBooked = Booking::where('room_id', $room->id)
            ->whereIn('status', ['pending', 'approved', 'confirmed'])
            ->where(function ($query) use ($validated) {
                $query->where('check_in', '<', $validated['check_out'])
                      ->where('check_out', '>', $validated['check_in']);
            })
            ->exists();

        if ($alreadyBooked) {
            return response()->json(['message' => 'Room is already booked for these dates.'], 422);
        }

        $roomType = RoomType::findOrFail($validated['room_type_id']);

        $checkIn = new \DateTime($validated['check_in']);
        $checkOut = new \DateTime($validated['check_out']);
        $nights = $checkIn->diff($checkOut)->days;

        $price = $roomType->price ?? $roomType->price_per_night ?? 0;
        $totalAmount = $price * $nights;

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'hotel_id' => $hotel->id,
            'room_type_id' => $roomType->id,
            'room_id' => $room->id,
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'guests' => $validated['guests'],
            'nights' => $nights,
            'total_amount' => $totalAmount,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Booking created successfully.',
            'data' => $booking
        ], 201);
    }

    /**
     * Get logged-in user's bookings
     */
    public function myBookings(Request $request)
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            ->with(['hotel', 'room', 'roomType', 'payment'])
            ->latest()
            ->paginate(10);

        return response()->json($bookings);
    }

    /**
     * Get booking detail
     */
    public function bookingDetail(Request $request, $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)
            ->with(['hotel', 'room', 'roomType', 'payment'])
            ->findOrFail($id);

        return response()->json(['data' => $booking]);
    }

    /**
     * Cancel booking
     */
    public function cancelBooking(Request $request, $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);

        if (!in_array($booking->status, ['pending', 'approved'])) {
            return response()->json(['message' => 'This booking cannot be cancelled.'], 422);
        }

        $booking->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Booking cancelled successfully.',
            'data' => $booking
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Payments (Protected Features)
    |--------------------------------------------------------------------------
    */

    /**
     * Create payment
     */
    public function createPayment(Request $request, $bookingId)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($bookingId);

        if ($booking->status !== 'approved') {
            return response()->json(['message' => 'Booking must be approved before payment.'], 422);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:cash,card,bank_transfer',
            'transaction_id' => 'nullable|string|max:255',
        ]);

        $existingPayment = Payment::where('booking_id', $booking->id)->first();
        if ($existingPayment) {
            return response()->json(['message' => 'Payment already exists.'], 422);
        }

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $request->user()->id,
            'amount' => $booking->total_amount,
            'payment_method' => $validated['payment_method'],
            'transaction_id' => $validated['transaction_id'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Payment created successfully.',
            'data' => $payment
        ], 201);
    }

    /**
     * Get payment info for booking
     */
    public function payment(Request $request, $bookingId)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($bookingId);
        $payment = Payment::where('booking_id', $booking->id)->first();

        return response()->json(['data' => $payment]);
    }

    /*
    |--------------------------------------------------------------------------
    | Reviews (Protected Features)
    |--------------------------------------------------------------------------
    */

    /**
     * Create review
     */
    public function createReview(Request $request, $hotelId)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $hasBooking = Booking::where('user_id', $request->user()->id)
            ->where('hotel_id', $hotelId)
            ->where('status', 'completed')
            ->exists();

        if (!$hasBooking) {
            return response()->json([
                'message' => 'You can only review a hotel after completing a booking.'
            ], 403);
        }

        $existingReview = Review::where('user_id', $request->user()->id)
            ->where('hotel_id', $hotelId)
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'You already reviewed this hotel.'], 422);
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'hotel_id' => $hotelId,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Review created successfully.',
            'data' => $review
        ], 201);
    }

    /**
     * Get user reviews
     */
    public function myReviews(Request $request)
    {
        $reviews = Review::where('user_id', $request->user()->id)
            ->with('hotel')
            ->latest()
            ->paginate(10);

        return response()->json($reviews);
    }

    /**
     * Update review
     */
    public function updateReview(Request $request, $id)
    {
        $review = Review::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review->update($validated);

        return response()->json([
            'message' => 'Review updated successfully.',
            'data' => $review
        ]);
    }

    /**
     * Delete review
     */
    public function deleteReview(Request $request, $id)
    {
        $review = Review::where('user_id', $request->user()->id)->findOrFail($id);
        $review->delete();

        return response()->json(['message' => 'Review deleted successfully.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications (Protected Features)
    |--------------------------------------------------------------------------
    */

    /**
     * Get user notifications
     */
    public function notifications(Request $request)
    {
        $notifications = UserNotification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($notifications);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationRead(Request $request, $id)
    {
        $notification = UserNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notification marked as read.']);
    }
}