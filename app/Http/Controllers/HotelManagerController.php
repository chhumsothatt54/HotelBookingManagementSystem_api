<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HotelManagerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    public function dashboard(Request $request)
    {
        $manager = $request->user();

        $hotel = Hotel::where('manager_id', $manager->id)->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'You do not have a hotel yet.'
            ], 404);
        }

        $totalRooms = Room::where('hotel_id', $hotel->id)->count();

        $availableRooms = Room::where('hotel_id', $hotel->id)
            ->where('status', 'available')
            ->count();

        $totalBookings = Booking::where('hotel_id', $hotel->id)->count();

        $pendingBookings = Booking::where('hotel_id', $hotel->id)
            ->where('status', 'pending')
            ->count();

        $approvedBookings = Booking::where('hotel_id', $hotel->id)
            ->where('status', 'approved')
            ->count();

        $revenue = Booking::where('hotel_id', $hotel->id)
            ->where('status', 'completed')
            ->sum('total_amount');

        $averageRating = Review::where('hotel_id', $hotel->id)
            ->avg('rating');

        return response()->json([
            'hotel' => $hotel,

            'statistics' => [
                'total_rooms' => $totalRooms,
                'available_rooms' => $availableRooms,
                'total_bookings' => $totalBookings,
                'pending_bookings' => $pendingBookings,
                'approved_bookings' => $approvedBookings,
                'revenue' => $revenue,
                'average_rating' => round($averageRating ?? 0, 2),
            ]
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Hotel
    |--------------------------------------------------------------------------
    */
    // public function storeHotel(Request $request)
    // {
    //     $manager = $request->user();

    //     // ឆែកមើលថាបើមាន Hotel រួចហើយ មិនឱ្យបង្កើតជាន់គ្នាទេ
    //     $existingHotel = Hotel::where('manager_id', $manager->id)->first();
    //     if ($existingHotel) {
    //         return response()->json([
    //             'message' => 'You already have a hotel.'
    //         ], 400);
    //     }

    //     $validated = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'description' => 'nullable|string',
    //         'phone' => 'nullable|string|max:30',
    //         'email' => 'nullable|email|max:255',
    //         'address' => 'required|string|max:500',
    //         'city' => 'nullable|string|max:100',
    //         'province' => 'nullable|string|max:100',
    //     ]);

    //     // ភ្ជាប់ manager_id ជាមួយ user ដែលកំពុង Login
    //     $validated['manager_id'] = $manager->id;

    //     $hotel = Hotel::create($validated);

    //     return response()->json([
    //         'message' => 'Hotel created successfully.',
    //         'data' => $hotel
    //     ], 201);
    // }

    public function myHotel(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->with(['roomTypes', 'rooms', 'amenities'])
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        return response()->json([
            'data' => $hotel
        ]);
    }


    public function updateHotel(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'phone' => 'sometimes|string|max:30',
            'email' => 'sometimes|email|max:255',
            'address' => 'sometimes|string|max:500',
            'city' => 'sometimes|string|max:100',
            'province' => 'sometimes|string|max:100',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
        ]);

        $hotel->update($validated);

        return response()->json([
            'message' => 'Hotel updated successfully.',
            'data' => $hotel
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Room Types
    |--------------------------------------------------------------------------
    */

    public function roomTypes(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $roomTypes = RoomType::where('hotel_id', $hotel->id)
            ->with('rooms')
            ->latest()
            ->get();

        return response()->json([
            'data' => $roomTypes
        ]);
    }


    // public function storeRoomType(Request $request)
    // {
    //     $hotel = Hotel::where('manager_id', $request->user()->id)
    //         ->firstOrFail();

    //     $validated = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'description' => 'nullable|string',
    //         'capacity' => 'required|integer|min:1',
    //         'price' => 'required|numeric|min:0',
    //     ]);

    //     $validated['hotel_id'] = $hotel->id;

    //     $roomType = RoomType::create($validated);

    //     return response()->json([
    //         'message' => 'Room type created successfully.',
    //         'data' => $roomType
    //     ], 201);
    // }


    public function updateRoomType(
        Request $request,
        $id
    ) {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $roomType = RoomType::where('hotel_id', $hotel->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
        ]);

        $roomType->update($validated);

        return response()->json([
            'message' => 'Room type updated successfully.',
            'data' => $roomType
        ]);
    }


    public function deleteRoomType(
        Request $request,
        $id
    ) {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $roomType = RoomType::where('hotel_id', $hotel->id)
            ->findOrFail($id);

        $roomType->delete();

        return response()->json([
            'message' => 'Room type deleted successfully.'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Rooms
    |--------------------------------------------------------------------------
    */

    public function rooms(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $rooms = Room::where('hotel_id', $hotel->id)
            ->with('roomType')
            ->latest()
            ->get();

        return response()->json([
            'data' => $rooms
        ]);
    }


    public function storeRoom(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'room_number' => 'required|string|max:50',
            'floor' => 'nullable|integer',
            'status' => 'required|in:available,occupied,maintenance,inactive',
        ]);

        $roomTypeExists = RoomType::where('id', $validated['room_type_id'])
            ->where('hotel_id', $hotel->id)
            ->exists();

        if (!$roomTypeExists) {
            return response()->json([
                'message' => 'Room type does not belong to your hotel.'
            ], 403);
        }

        $validated['hotel_id'] = $hotel->id;

        $room = Room::create($validated);

        return response()->json([
            'message' => 'Room created successfully.',
            'data' => $room
        ], 201);
    }


    public function updateRoom(
        Request $request,
        $id
    ) {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $room = Room::where('hotel_id', $hotel->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'room_type_id' => 'sometimes|exists:room_types,id',
            'room_number' => 'sometimes|string|max:50',
            'floor' => 'nullable|integer',
            'status' => 'sometimes|in:available,occupied,maintenance,inactive',
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Room updated successfully.',
            'data' => $room
        ]);
    }


    public function deleteRoom(
        Request $request,
        $id
    ) {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $room = Room::where('hotel_id', $hotel->id)
            ->findOrFail($id);

        $room->delete();

        return response()->json([
            'message' => 'Room deleted successfully.'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Amenities
    |--------------------------------------------------------------------------
    */

    public function amenities(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'data' => $hotel->amenities
        ]);
    }


    public function attachAmenity(
        Request $request,
        $amenityId
    ) {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $amenity = Amenity::findOrFail($amenityId);

        $hotel->amenities()->syncWithoutDetaching([
            $amenity->id
        ]);

        return response()->json([
            'message' => 'Amenity added successfully.'
        ]);
    }


    public function detachAmenity(
        Request $request,
        $amenityId
    ) {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $hotel->amenities()->detach($amenityId);

        return response()->json([
            'message' => 'Amenity removed successfully.'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Bookings
    |--------------------------------------------------------------------------
    */

    public function bookings(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $bookings = Booking::where('hotel_id', $hotel->id)
            ->with([
                'user',
                'room',
                'roomType'
            ])
            ->latest()
            ->paginate(15);

        return response()->json($bookings);
    }


    public function showBooking(
        Request $request,
        $id
    ) {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $booking = Booking::where('hotel_id', $hotel->id)
            ->with([
                'user',
                'room',
                'roomType',
                'payment'
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $booking
        ]);
    }


    public function updateBookingStatus(
        Request $request,
        $id
    ) {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $booking = Booking::where('hotel_id', $hotel->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,cancelled,completed',
        ]);

        $booking->update([
            'status' => $validated['status']
        ]);

        return response()->json([
            'message' => 'Booking status updated successfully.',
            'data' => $booking
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */

    public function reviews(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $reviews = Review::where('hotel_id', $hotel->id)
            ->with('user')
            ->latest()
            ->paginate(15);

        return response()->json($reviews);
    }


    /*
    |--------------------------------------------------------------------------
    | Revenue Report
    |--------------------------------------------------------------------------
    */

    public function revenueReport(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $revenue = Booking::where('hotel_id', $hotel->id)
            ->where('status', 'completed')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'hotel' => $hotel->name,
            'data' => $revenue
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Occupancy Report
    |--------------------------------------------------------------------------
    */

    public function occupancyReport(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->firstOrFail();

        $totalRooms = Room::where('hotel_id', $hotel->id)
            ->count();

        $occupiedRooms = Room::where('hotel_id', $hotel->id)
            ->where('status', 'occupied')
            ->count();

        $occupancyRate = $totalRooms > 0
            ? round(($occupiedRooms / $totalRooms) * 100, 2)
            : 0;

        return response()->json([
            'hotel' => $hotel->name,
            'total_rooms' => $totalRooms,
            'occupied_rooms' => $occupiedRooms,
            'available_rooms' => $totalRooms - $occupiedRooms,
            'occupancy_rate' => $occupancyRate . '%',
        ]);
    }
    
}

