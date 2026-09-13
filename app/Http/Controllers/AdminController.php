<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /*
     * |--------------------------------------------------------------------------
     * | Dashboard
     * |--------------------------------------------------------------------------
     */
    public function dashboard()
    {
        $totalUsers = User::count();

        $totalCustomers = User::where('role', 'customer')->count();

        $totalManagers = User::where(
            'role',
            'hotel_manager'
        )->count();

        $totalHotels = Hotel::count();
        $pendingHotels = Hotel::where(
            'status',
            'pending'
        )->count();

        $totalBookings = Booking::count();

        $totalPayments = Payment::where('status', 'paid')->count();
        $totalRevenue = Payment::where(
            'status',
            'paid'
        )->sum('amount');
        $totalRooms = Room::count();

        return response()->json([
            'result' => true,
            'message' => 'Admin Dashboard',
            'data' => [
                'total_users' => $totalUsers,
                'total_customers' => $totalCustomers,
                'total_manager' => $totalManagers,
                'total_hotels' => $totalHotels,
                'pending_hotels' => $pendingHotels,
                'total_bookings' => $totalBookings,
                'total_payments' => $totalPayments,
                'total_revenue' => $totalRevenue,
                'total_rooms'=>$totalRooms
            ],
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Manage Users
     * |--------------------------------------------------------------------------
     */

    public function users(Request $request)
    {
        $query = User::whereIn('role', ['customer', 'hotel_manager']);

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(15);

        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => $users,
        ]);
    }

    public function showUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ]);
        }

        return response()->json([
            'result' => 'successfully',
            'data' => $user,
        ]);
    }

    public function updateUserStatus(
        Request $request,
        $id
    ) {
        $request->validate([
            'status' => [
                'required',
                'in:active,inactive,blocked',
            ],
        ]);
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }
        $user->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'User status updated successfully',
            'data' => $user,
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Manage Hotel Managers
     * |--------------------------------------------------------------------------
     */

public function managers(Request $request)
    {
        $query = User::where('role', 'hotel_manager')->with('hotels');
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $managers = $query->latest()->paginate(10);

        return response()->json([
            'message' => 'Hotel managers retrieved successfully',
            'data' => $managers,
        ]);
    }

    public function updateManagerStatus(
        Request $request,
        $id
    ) {
        $request->validate([
            'status' => [
                'required',
                'in:active,inactive,blocked',
            ],
        ]);

        $manager = User::where(
            'role',
            'hotel_manager'
        )->find($id);

        if (!$manager) {
            return response()->json([
                'message' => 'Hotel manager not found',
            ], 404);
        }

        $manager->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Manager status updated successfully',
            'data' => $manager,
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Manage Hotels
     * |--------------------------------------------------------------------------
     */

    public function hotels()
    {
        $hotels = Hotel::with('manager')
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Hotels retrieved successfully',
            'data' => $hotels,
        ]);
    }

    public function showHotel($id)
    {
        $hotel = Hotel::with([
            'manager',
            'images',
            'roomTypes',
            'rooms',
        ])->find($id);

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found',
            ], 404);
        }

        return response()->json([
            'data' => $hotel,
        ]);
    }

    public function updateHotelStatus(
        Request $request,
        $id
    ) {
        $request->validate([
            'status' => [
                'required',
                'in:pending,approved,rejected,inactive',
            ],
        ]);

        $hotel = Hotel::find($id);

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found',
            ], 404);
        }

        $hotel->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Hotel status updated successfully',
            'data' => $hotel,
        ]);
    }

    public function deleteHotel($id)
    {
        $hotel = Hotel::find($id);

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found',
            ], 404);
        }

        $hotel->delete();

        return response()->json([
            'message' => 'Hotel deleted successfully',
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Manage Room Types
     * |--------------------------------------------------------------------------
     */

    public function roomTypes()
    {
        $roomType = RoomType::with('hotel')->latest()->paginate(10);

        return response()->json([
            'message' => 'Room Type retrieved successfully',
            'data' => $roomType,
        ]);
    }

    public function addRoomType(Request $request)
    {
        $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_guests' => 'required|integer',
            'price_per_night' => 'required|numeric',
            'status' => 'required|string',
        ]);

        $roomType = RoomType::create($request->all());

        return response()->json([
            'message' => 'Room Type created successfully',
            'data' => $roomType,
        ], 201);
    }

    public function updateRoomType(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'max_guests' => 'sometimes|integer',
            'price_per_night' => 'sometimes|numeric',
            'status' => 'sometimes|string',
        ]);

        $roomType = RoomType::find($id);

        if (!$roomType) {
            return response()->json([
                'message' => 'Room Type not found',
            ], 404);
        }

        $roomType->update($request->all());

        return response()->json([
            'message' => 'Room Type updated successfully',
            'data' => $roomType,
        ]);
    }

    public function deleteRoomType($id)
    {
        $roomType = RoomType::find($id);

        if (!$roomType) {
            return response()->json([
                'message' => 'Room Type not found',
            ], 404);
        }

        $roomType->delete();

        return response()->json([
            'message' => 'Room Type deleted successfully',
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Manage Rooms
     * |--------------------------------------------------------------------------
     */

    public function rooms()
    {
        $room = Room::with('hotel', 'roomType', 'amenities')
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Room retrieved successfully',
            'data' => $room,
        ]);
    }

    public function addRoom(Request $request)
    {
        $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'room_type_id' => 'required|exists:room_types,id',
            'room_number' => 'required|string|max:20',
            'floor' => 'nullable|integer',
            'status' => 'required|in:available,maintenance,inactive',
            'price_per_night' => 'nullable|numeric',
        ]);

        $existingRoom = Room::where('hotel_id', $request->hotel_id)
            ->where('room_number', $request->room_number)
            ->first();

        if ($existingRoom) {
            return response()->json([
                'message' => 'Room number already exists in this hotel',
            ], 422);
        }

        $room = Room::create($request->all());

        if ($request->has('amenities')) {
            $room->amenities()->sync($request->amenities);
        }

        return response()->json([
            'message' => 'Room created successfully',
            'data' => $room->load('hotel', 'roomType', 'amenities'),
        ], 201);
    }

    public function updateRoom(Request $request, $id)
    {
        $request->validate([
            'hotel_id' => 'sometimes|exists:hotels,id',
            'room_type_id' => 'sometimes|exists:room_types,id',
            'room_number' => 'sometimes|string|max:20',
            'floor' => 'nullable|integer',
            'status' => 'sometimes|in:available,maintenance,inactive',
            'price_per_night' => 'sometimes|numeric',
        ]);

        $room = Room::find($id);

        if (!$room) {
            return response()->json([
                'message' => 'Room not found',
            ], 404);
        }

        if ($request->has('room_number') && $request->room_number !== $room->room_number) {
            $hotelId = $request->hotel_id ?? $room->hotel_id;
            $existingRoom = Room::where('hotel_id', $hotelId)
                ->where('room_number', $request->room_number)
                ->first();

            if ($existingRoom) {
                return response()->json([
                    'message' => 'Room number already exists in this hotel',
                ], 422);
            }
        }

        $room->update($request->all());

        if ($request->has('amenities')) {
            $room->amenities()->sync($request->amenities);
        }

        return response()->json([
            'message' => 'Room updated successfully',
            'data' => $room->load('hotel', 'roomType', 'amenities'),
        ]);
    }

    public function deleteRoom($id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json([
                'message' => 'Room not found',
            ], 404);
        }

        $room->delete();

        return response()->json([
            'message' => 'Room deleted successfully',
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Manage Amenities
     * |--------------------------------------------------------------------------
     */

    public function amenities()
    {
        $amenity = Amenity::with('rooms')
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Amenity Retrieved Successfully',
            'data' => $amenity,
        ]);
    }

    public function addAmenity(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'icon' => 'string|max:150',
            'description' => 'required|string',
            'status' => 'required|in:active,inactive',
        ]);

        $amenity = Amenity::create($request->all());

        return response()->json([
            'message' => 'Amenity created successfully',
            'data' => $amenity,
        ], 201);
    }

    public function updateAmenity(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:200',
            'icon' => 'sometimes|string|max:150',
            'description' => 'sometimes|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $amenity = Amenity::find($id);

        if (!$amenity) {
            return response()->json([
                'message' => 'Amenity not found',
            ], 404);
        }

        $amenity->update($request->all());

        return response()->json([
            'message' => 'Amenity updated successfully',
            'data' => $amenity,
        ]);
    }

    public function deleteAmenity($id)
    {
        $amenity = Amenity::find($id);

        if (!$amenity) {
            return response()->json([
                'message' => 'Amenity not found',
            ], 404);
        }

        $amenity->delete();

        return response()->json([
            'message' => 'Amenity deleted successfully',
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Manage Bookings
     * |--------------------------------------------------------------------------
     */

    public function bookings()
    {
        $bookings = Booking::with([
            'customer',
            'hotel',
            'room',
            'payments',
        ])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Bookings retrieved successfully',
            'data' => $bookings,
        ]);
    }

    public function showBooking($id)
    {
        $booking = Booking::with([
            'customer',
            'hotel',
            'room',
            'payments',
            'review',
        ])->find($id);

        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found',
            ], 404);
        }

        return response()->json([
            'data' => $booking,
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Manage Payments
     * |--------------------------------------------------------------------------
     */

    public function payments()
    {
        $payments = Payment::with([
            'booking.customer',
            'booking.hotel',
        ])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Payments retrieved successfully',
            'data' => $payments,
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Manage Reviews
     * |--------------------------------------------------------------------------
     */

    public function reviews()
    {
        $reviews = Review::with([
            'customer',
            'hotel',
            'booking',
        ])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Reviews retrieved successfully',
            'data' => $reviews,
        ]);
    }

    public function updateReviewStatus(
        Request $request,
        $id
    ) {
        $request->validate([
            'status' => [
                'required',
                'in:pending,approved,hidden',
            ],
        ]);

        $review = Review::find($id);

        if (!$review) {
            return response()->json([
                'message' => 'Review not found',
            ], 404);
        }

        $review->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Review status updated successfully',
            'data' => $review,
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Revenue Report
     * |--------------------------------------------------------------------------
     */

    public function revenueReport()
    {
        $totalRevenue = Payment::where('status', 'paid')
            ->sum('amount');

        $monthlyRevenue = Payment::select(
            DB::raw('EXTRACT(MONTH FROM paid_at) as month'),
            DB::raw('EXTRACT(YEAR FROM paid_at) as year'),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'paid')
            ->groupBy(
                DB::raw('EXTRACT(YEAR FROM paid_at)'),
                DB::raw('EXTRACT(MONTH FROM paid_at)')
            )
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return response()->json([
            'message' => 'Revenue report retrieved successfully',
            'total_revenue' => $totalRevenue,
            'monthly_revenue' => $monthlyRevenue
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Occupancy Report
     * |--------------------------------------------------------------------------
     */

    public function occupancyReport()
    {
        $totalRooms = Room::count();

        $occupiedRooms = Booking::whereIn(
            'status',
            ['confirmed', 'checked_in']
        )
            ->distinct('room_id')
            ->count('room_id');

        $occupancyRate = $totalRooms > 0
            ? round(
                ($occupiedRooms / $totalRooms) * 100,
                2
            )
            : 0;

        return response()->json([
            'total_rooms' => $totalRooms,
            'occupied_rooms' => $occupiedRooms,
            'occupancy_rate' => $occupancyRate . '%',
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Manage Notifications
     * |--------------------------------------------------------------------------
     */

    public function notifications()
    {
        $notifications = UserNotification::with('user')
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Notifications retrieved successfully',
            'data' => $notifications,
        ]);
    }

    /*
     * |--------------------------------------------------------------------------
     * | Audit Logs
     * |--------------------------------------------------------------------------
     */

    public function auditLogs()
    {
        return response()->json([
            'message' => 'Audit log feature will be available when audit_logs table is added',
        ]);
    }
}
