<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\RefundRequest;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * 40 - 44: Search, Filter & Sort Hotels
     */
    public function hotels(Request $request)
    {
        $query = Hotel::where('status', 'approved')
            ->withAvg(['reviews' => function ($q) {
                $q->where('status', 'approved');
            }], 'rating')
            ->with(['roomTypes', 'images']); // ដក 'amenities' ចេញពីទីនេះ

        // Filter by Location/City
        if ($request->filled('city')) {
            $query->where('city', 'ilike', '%' . $request->city . '%');
        }

        // Filter by Room Type
        if ($request->filled('room_type_id')) {
            $query->whereHas('roomTypes', function ($q) use ($request) {
                $q->where('id', $request->room_type_id);
            });
        }

        // Filter by Price Range
        if ($request->filled('min_price') || $request->filled('max_price')) {
            $min = $request->input('min_price', 0);
            $max = $request->input('max_price', PHP_INT_MAX);

            $query->whereHas('roomTypes', function ($q) use ($min, $max) {
                $q->whereBetween('price_per_night', [$min, $max]);
            });
        }

        // Sorting Hotels
        if ($request->filled('sort')) {
            switch ($request->sort) {
                case 'price_asc':
                    $query->withMin('roomTypes', 'price_per_night')
                          ->orderBy('room_types_min_price_per_night', 'asc');
                    break;
                case 'price_desc':
                    $query->withMax('roomTypes', 'price_per_night')
                          ->orderBy('room_types_max_price_per_night', 'desc');
                    break;
                case 'rating':
                    $query->orderByRaw('reviews_avg_rating DESC NULLS LAST');
                    break;
                case 'newest':
                    $query->latest();
                    break;
                default:
                    $query->latest();
            }
        } else {
            $query->latest();
        }

        return response()->json($query->paginate(12));
    }

    /**
     * Search Rooms by Amenities (Safe for PostgreSQL)
     */
    public function searchRooms(Request $request)
    {
        $query = Room::with(['hotel', 'amenities', 'roomType']);

        if ($request->filled('amenities')) {
            $amenities = is_array($request->amenities) 
                ? $request->amenities 
                : explode(',', $request->amenities);

            // បំបែករវាង IDs (លេខ) និង Names (អក្សរ) ដើម្បីការពារ SQL Syntax Error លើ PostgreSQL
            $numericIds = array_filter($amenities, 'is_numeric');
            $stringNames = array_filter($amenities, fn($val) => !is_numeric($val));

            $query->whereHas('amenities', function ($q) use ($numericIds, $stringNames) {
                $q->where(function ($subQuery) use ($numericIds, $stringNames) {
                    if (!empty($numericIds)) {
                        $subQuery->orWhereIn('amenities.id', $numericIds);
                    }
                    if (!empty($stringNames)) {
                        $subQuery->orWhereIn('amenities.name', $stringNames);
                    }
                });
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price_per_night', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price_per_night', '<=', $request->max_price);
        }

        return response()->json([
            'message' => 'Rooms retrieved successfully.',
            'data'    => $query->paginate($request->get('per_page', 10))
        ], 200);
    }

    /**
     * Search Rooms by Room Type / Room Specs
     */
    public function searchByRoom(Request $request)
    {
        $query = Room::with(['hotel', 'amenities', 'roomType']);

        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        if ($request->filled('max_guests')) {
            $query->whereHas('roomType', function ($q) use ($request) {
                $q->where('max_guests', '>=', $request->max_guests);
            });
        }

        return response()->json([
            'message' => 'Rooms retrieved successfully.',
            'data'    => $query->paginate($request->get('per_page', 10))
        ], 200);
    }

    /**
     * Search Rooms by Location / City
     */
    public function searchByLocation(Request $request)
    {
        $query = Room::with(['hotel', 'amenities', 'roomType']);

        if ($request->filled('city')) {
            $query->whereHas('hotel', function ($q) use ($request) {
                $q->where('city', 'ilike', '%' . $request->city . '%');
            });
        }

        return response()->json([
            'message' => 'Rooms retrieved successfully.',
            'data'    => $query->paginate($request->get('per_page', 10))
        ], 200);
    }

    /**
     * 45: View Hotel
     */
    public function hotelDetail($id)
    {
        $hotel = Hotel::where('status', 'approved')
            ->with([
                'images' => function ($q) {
                    $q->select('id', 'hotel_id', 'image as image_url', 'is_primary');
                },
                'roomTypes' => function ($q) {
                    $q->select('id', 'hotel_id', 'name', 'max_guests', 'price_per_night');
                }
            ])
            ->withAvg(['reviews' => function ($q) {
                $q->where('status', 'approved');
            }], 'rating')
            ->findOrFail($id);

        return response()->json([
            'id'          => $hotel->id,
            'name'        => $hotel->name,
            'description' => $hotel->description,
            'city'        => $hotel->city,
            'rating'      => (float) ($hotel->reviews_avg_rating ?? 0),
            'images'      => $hotel->images,
            'room_types'  => $hotel->roomTypes,
        ]);
    }

    /**
     * 46: View Rooms
     */
    public function hotelRooms($hotelId)
    {
        $rooms = Room::where('hotel_id', $hotelId)
            ->with('roomType')
            ->get();

        return response()->json(['data' => $rooms]);
    }

    /**
     * 47: Check Room Availability
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'hotel_id'  => 'required|exists:hotels,id',
            'check_in'  => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        $checkIn = $request->check_in;
        $checkOut = $request->check_out;

        $availableRooms = Room::where('hotel_id', $request->hotel_id)
            ->where('status', 'available')
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->whereIn('status', ['pending', 'approved', 'confirmed'])
                  ->where('check_in', '<', $checkOut)
                  ->where('check_out', '>', $checkIn);
            })
            ->with('roomType')
            ->get();

        return response()->json([
            'available_count' => $availableRooms->count(),
            'rooms'           => $availableRooms
        ]);
    }

    /**
     * 48: Book Room (Laravel Auto Calculates Nights & Total)
     */
    public function createBooking(Request $request)
    {
        $validated = $request->validate([
            'hotel_id'     => 'required|exists:hotels,id',
            'room_id'      => 'required|exists:rooms,id',
            'guest_name'   => 'required|string|max:100',
            'guest_phone'  => 'required|string|max:20',
            'guest_email'  => 'required|email|max:150',
            'check_in'     => 'required|date|after_or_equal:today',
            'check_out'    => 'required|date|after:check_in',
            'total_guests' => 'required|integer|min:1',
        ]);

        $room = Room::with('roomType')->where('id', $validated['room_id'])
            ->where('hotel_id', $validated['hotel_id'])
            ->firstOrFail();

        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);
        $nights = $checkIn->diffInDays($checkOut);

        $pricePerNight = $room->roomType->price_per_night ?? $room->price_per_night;
        $totalAmount = $pricePerNight * $nights;

        $bookingNumber = 'BK-' . Carbon::parse($validated['check_in'])->format('Ymd') . '-' . Str::padLeft(mt_rand(1, 9999), 4, '0');

        $booking = Booking::create([
            'booking_number'  => $bookingNumber,
            'customer_id'     => $request->user()->id,
            'hotel_id'        => $validated['hotel_id'],
            'room_id'         => $validated['room_id'],
            'guest_name'      => $validated['guest_name'],
            'guest_phone'     => $validated['guest_phone'],
            'guest_email'     => $validated['guest_email'],
            'check_in'        => $validated['check_in'],
            'check_out'       => $validated['check_out'],
            'total_guests'    => $validated['total_guests'],
            'price_per_night' => $pricePerNight,
            'nights'          => $nights,
            'total_amount'    => $totalAmount,
            'status'          => 'pending',
        ]);

        return response()->json([
            'message' => 'Booking created successfully.',
            'data'    => $booking
        ], 201);
    }

    /**
     * 49: Payment
     */
    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'booking_id'     => 'required|exists:bookings,id',
            'amount'         => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'transaction_id' => 'required|string|unique:payments,transaction_id',
        ]);

        $booking = Booking::where('customer_id', $request->user()->id)
            ->findOrFail($validated['booking_id']);

        $payment = Payment::create([
    'booking_id' => $booking->id,
    'amount' => $request->amount,
    'payment_method' => $request->payment_method, // ប្រើ 'aba', 'acleda', 'cash' ជាដើម
    'transaction_id' => $request->transaction_id,
    'status' => 'paid', // <-- ត្រូវប្រើ 'paid' មិនមែន 'completed' ទេ
    'paid_at'        => now(), // <-- បន្ថែមបន្ទាត់នេះដើម្បីសរសេរកាលបរិច្ឆេទ/ម៉ោងបច្ចុប្បន្ន
]);

        $booking->update(['status' => 'confirmed']);

        return response()->json([
            'message' => 'Payment processed successfully.',
            'data'    => $payment
        ], 201);
    }

    /**
     * 50: Booking Confirmation
     */
    public function bookingConfirmation(Request $request, $id)
    {
        $booking = Booking::where('customer_id', $request->user()->id)
            ->with(['hotel', 'room.roomType'])
            ->findOrFail($id);

        return response()->json([
            'booking_number' => $booking->booking_number,
            'hotel'          => [
                'name' => $booking->hotel->name,
            ],
            'room'           => [
                'room_number' => $booking->room->room_number ?? (string)$booking->room->id,
                'room_type'   => $booking->room->roomType->name ?? 'Standard',
            ],
            'check_in'     => $booking->check_in->format('Y-m-d'),
            'check_out'    => $booking->check_out->format('Y-m-d'),
            'nights'       => $booking->nights,
            'total_amount' => (float)$booking->total_amount,
            'status'       => $booking->status,
        ]);
    }

    /**
     * 51: Booking History
     */
    public function bookingHistory(Request $request)
    {
        $bookings = Booking::where('customer_id', $request->user()->id)
            ->with(['hotel', 'room.roomType'])
            ->latest()
            ->paginate(10);

        return response()->json($bookings);
    }

    /**
     * 52: Booking Details
     */
    public function bookingDetail(Request $request, $id)
    {
        $booking = Booking::where('customer_id', $request->user()->id)
            ->with(['hotel', 'room.roomType', 'payments'])
            ->findOrFail($id);

        return response()->json(['data' => $booking]);
    }

    /**
     * 53: Cancel Booking
     */
    public function cancelBooking(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $booking = Booking::where('customer_id', $request->user()->id)->findOrFail($id);

        if (in_array($booking->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'This booking cannot be cancelled.'], 422);
        }

        $booking->update([
            'status'        => 'cancelled',
            'cancel_reason' => $validated['reason'],
        ]);

        return response()->json([
            'message' => 'Booking cancelled successfully.',
            'data'    => $booking
        ]);
    }

    /**
     * 54: Request Refund
     */
    public function requestRefund(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $booking = Booking::where('customer_id', $request->user()->id)->findOrFail($id);

        $refund = RefundRequest::create([
            'booking_id'  => $booking->id,
            'customer_id' => $request->user()->id,
            'amount'      => $validated['amount'],
            'reason'      => $validated['reason'],
            'status'      => 'pending',
        ]);

        return response()->json([
            'message' => 'Refund request submitted.',
            'data'    => $refund
        ], 201);
    }

    /**
     * 55: Review Hotel
     */
    public function createReview(Request $request, $hotelId)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'nullable|string|max:1000',
        ]);

        $booking = Booking::where('id', $validated['booking_id'])
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();

        $review = Review::create([
            'customer_id' => $request->user()->id,
            'hotel_id'    => $hotelId,
            'booking_id'  => $booking->id,
            'rating'      => $validated['rating'],
            'comment'     => $validated['comment'] ?? null,
            'status'      => 'pending',
        ]);

        return response()->json([
            'message' => 'Review submitted successfully.',
            'data'    => $review
        ], 201);
    }

    /**
     * 56: Profile Get & Update
     */
    public function getProfile(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'phone'  => 'sometimes|string|max:20',
            'email'  => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'avatar' => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data'    => $user
        ], 200);
    }

    /**
     * 57: Notifications (Get, Read One, Read All)
     */
    public function getNotifications(Request $request)
    {
        $notifications = UserNotification::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Notifications retrieved successfully.',
            'data'    => $notifications
        ], 200);
    }

    public function readNotification(Request $request, $id)
    {
        $notification = UserNotification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found or access denied.'
            ], 404);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notification marked as read.',
            'data'    => $notification
        ], 200);
    }

    public function readAllNotifications(Request $request)
    {
        $updatedCount = UserNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message'       => 'All notifications marked as read.',
            'updated_count' => $updatedCount
        ], 200);
    }
}