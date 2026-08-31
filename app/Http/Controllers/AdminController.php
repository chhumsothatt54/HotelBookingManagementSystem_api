<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
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
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Manage Users
    |--------------------------------------------------------------------------
    */

    public function users()
    {
        $users = User::where('role', 'customer')
            ->latest()
            ->paginate(15);

        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => $users,
        ]);
    }

    public function showUser($id)
    {
        $user = User::find($id);
        if (! $user) {
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
        if (! $user) {
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
    |--------------------------------------------------------------------------
    | Manage Hotel Managers
    |--------------------------------------------------------------------------
    */

    public function managers()
    {
        $managers = User::where(
            'role',
            'hotel_manager'
        )
            ->with('hotels')
            ->latest()
            ->paginate(10);

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

        if (! $manager) {
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
    |--------------------------------------------------------------------------
    | Manage Hotels
    |--------------------------------------------------------------------------
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

        if (! $hotel) {
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

        if (! $hotel) {
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

    /*
    |--------------------------------------------------------------------------
    | Manage Room Types
    |--------------------------------------------------------------------------
    */

    public function roomTypes()
    {
        $roomType = RoomType::with('hotel')->latest()->paginate(10);

        return response()->json([
            'message' => 'Room Type retrieved successfully',
            'data' => $roomType,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Manage Rooms
    |--------------------------------------------------------------------------
    */

    public function rooms()
    {
        $room = Room::with('hotel', 'roomType', 'amenities')
            ->latest()->paginate(10);

        return response()->json([
            'message' => 'Room retrieved successfully',
            'data' => $room,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Manage Amenities
    |--------------------------------------------------------------------------
    */

    public function amenities()
    {
        $amenity = Amenity::with('room_amenities', 'rooms')
            ->latest()->paginate(10);

        return response()->json([
            'message' => 'Amenity Retrieved Successfully',
            'data' => $amenity,
        ]);
    }

    //====noted

    /*
    |--------------------------------------------------------------------------
    | Manage Bookings
    |--------------------------------------------------------------------------
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

        if (! $booking) {
            return response()->json([
                'message' => 'Booking not found',
            ], 404);
        }

        return response()->json([
            'data' => $booking,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Manage Payments
    |--------------------------------------------------------------------------
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
    |--------------------------------------------------------------------------
    | Manage Reviews
    |--------------------------------------------------------------------------
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

        if (! $review) {
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
    |--------------------------------------------------------------------------
    | Revenue Report
    |--------------------------------------------------------------------------
    */

    public function revenueReport()
    {
        $totalRevenue = Payment::where(
            'status',
            'paid'
        )->sum('amount');

        $monthlyRevenue = Payment::select(
            DB::raw('MONTH(paid_at) as month'),
            DB::raw('YEAR(paid_at) as year'),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'paid')
            ->groupBy(
                DB::raw('YEAR(paid_at)'),
                DB::raw('MONTH(paid_at)')
            )
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return response()->json([
            'total_revenue' => $totalRevenue,
            'monthly_revenue' => $monthlyRevenue,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Occupancy Report
    |--------------------------------------------------------------------------
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
            'occupancy_rate' => $occupancyRate.'%',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Manage Notifications
    |--------------------------------------------------------------------------
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
    |--------------------------------------------------------------------------
    | Audit Logs
    |--------------------------------------------------------------------------
    */

    public function auditLogs()
    {
        return response()->json([
            'message' => 'Audit log feature will be available when audit_logs table is added',
        ]);
    }
}
