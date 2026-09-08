<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\Carbon;
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
                'result' => false,
                'message' => 'Hotel not found.',
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
            'result' => true,
            'message' => 'Dashboard data retrieved successfully.',
            'data' => [
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

    public function myHotel(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->with([
                'roomTypes',
                'rooms.amenities'
            ])
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


    public function storeHotel(Request $request)
    {
        $manager = $request->user();

        /*
        |--------------------------------------------------------------------------
        | One manager = One hotel
        |--------------------------------------------------------------------------
        */

        $existingHotel = Hotel::where('manager_id', $manager->id)->first();

        if ($existingHotel) {
            return response()->json([
                'message' => 'You already have a hotel.'
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'province' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $validated['manager_id'] = $manager->id;

        $hotel = Hotel::create($validated);

        return response()->json([
            'result' => true,
            'message' => 'Hotel created successfully.',
            'data' => $hotel
        ], 201);
    }


    public function updateHotel(Request $request, $id)
    {
        $hotel = Hotel::where('id', $id)
            ->where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:100',
            'country' => 'sometimes|required|string|max:100',
            'province' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $hotel->update($validated);

        return response()->json([
            'result' => true,
            'message' => 'Hotel updated successfully.',
            'data' => $hotel
        ]);
    }


    public function uploadImage(Request $request)
    {
        $manager = $request->user();

        $hotel = Hotel::where('manager_id', $manager->id)->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_primary' => 'nullable'
        ]);

        $path = $request->file('image')->store('hotels', 'public');

        $isPrimary = filter_var(
            $request->input('is_primary', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($isPrimary) {
            HotelImage::where('hotel_id', $hotel->id)
                ->update([
                    'is_primary' => false
                ]);
        }

        $hotelImage = HotelImage::create([
            'hotel_id' => $hotel->id,
            'image_path' => $path,
            'is_primary' => $isPrimary,
        ]);

        return response()->json([
            'result' => true,
            'message' => 'Hotel image uploaded successfully.',
            'data' => [
                'id' => $hotelImage->id,
                'hotel_id' => $hotelImage->hotel_id,
                'image_path' => $hotelImage->image_path,
                'is_primary' => $hotelImage->is_primary,
                'url' => asset('storage/' . $path),
            ]
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | Room Types
    |--------------------------------------------------------------------------
    */

    public function roomTypes(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $roomTypes = RoomType::where('hotel_id', $hotel->id)
            ->with('rooms')
            ->get();

        return response()->json([
            'result' => true,
            'data' => $roomTypes
        ]);
    }


    public function storeRoomType(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        $validated['hotel_id'] = $hotel->id;

        $roomType = RoomType::create($validated);

        return response()->json([
            'result' => true,
            'message' => 'Room type created successfully.',
            'data' => $roomType
        ], 201);
    }


    public function updateRoomType(Request $request, $id)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $roomType = RoomType::where('id', $id)
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$roomType) {
            return response()->json([
                'message' => 'Room type not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'sometimes|required|integer|min:1',
            'price' => 'sometimes|required|numeric|min:0',
        ]);

        $roomType->update($validated);

        return response()->json([
            'result' => true,
            'message' => 'Room type updated successfully.',
            'data' => $roomType
        ]);
    }


    public function deleteRoomType(Request $request, $id)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $roomType = RoomType::where('id', $id)
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$roomType) {
            return response()->json([
                'message' => 'Room type not found.'
            ], 404);
        }

        $roomType->delete();

        return response()->json([
            'result' => true,
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
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $rooms = Room::where('hotel_id', $hotel->id)
            ->with('roomType')
            ->get();

        return response()->json([
            'result' => true,
            'data' => $rooms
        ]);
    }


    public function storeRoom(Request $request)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $validated = $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'room_number' => 'required|string|max:50',
            'floor' => 'nullable|integer',
            'status' => 'required|in:available,occupied,maintenance,inactive',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Make sure room type belongs to manager's hotel
        |--------------------------------------------------------------------------
        */

        $roomType = RoomType::where('id', $validated['room_type_id'])
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$roomType) {
            return response()->json([
                'message' => 'The selected room type does not belong to your hotel.'
            ], 403);
        }

        $validated['hotel_id'] = $hotel->id;

        $room = Room::create($validated);

        return response()->json([
            'result' => true,
            'message' => 'Room created successfully.',
            'data' => $room
        ], 201);
    }


    public function updateRoom(Request $request, $id)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $room = Room::where('id', $id)
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$room) {
            return response()->json([
                'message' => 'Room not found.'
            ], 404);
        }

        $validated = $request->validate([
            'room_type_id' => 'sometimes|required|exists:room_types,id',
            'room_number' => 'sometimes|required|string|max:50',
            'floor' => 'nullable|integer',
            'status' => 'sometimes|required|in:available,occupied,maintenance,inactive',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Check updated room type belongs to this hotel
        |--------------------------------------------------------------------------
        */

        if (isset($validated['room_type_id'])) {
            $roomType = RoomType::where('id', $validated['room_type_id'])
                ->where('hotel_id', $hotel->id)
                ->first();

            if (!$roomType) {
                return response()->json([
                    'message' => 'The selected room type does not belong to your hotel.'
                ], 403);
            }
        }

        $room->update($validated);

        return response()->json([
            'result' => true,
            'message' => 'Room updated successfully.',
            'data' => $room
        ]);
    }


    public function deleteRoom(Request $request, $id)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $room = Room::where('id', $id)
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$room) {
            return response()->json([
                'message' => 'Room not found.'
            ], 404);
        }

        $room->delete();

        return response()->json([
            'result' => true,
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
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $amenities = Amenity::whereHas('rooms', function ($query) use ($hotel) {
            $query->where('hotel_id', $hotel->id);
        })->get();

        return response()->json([
            'result' => true,
            'data' => $amenities
        ]);
    }


    public function attachAmenity(Request $request, $amenityId)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $amenity = Amenity::find($amenityId);

        if (!$amenity) {
            return response()->json([
                'message' => 'Amenity not found.'
            ], 404);
        }

        $hotel->amenities()->syncWithoutDetaching([
            $amenity->id
        ]);

        return response()->json([
            'result' => true,
            'message' => 'Amenity attached successfully.'
        ]);
    }


    public function detachAmenity(Request $request, $amenityId)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $hotel->amenities()->detach($amenityId);

        return response()->json([
            'result' => true,
            'message' => 'Amenity detached successfully.'
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
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

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


    public function showBooking(Request $request, $id)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $booking = Booking::where('id', $id)
            ->where('hotel_id', $hotel->id)
            ->with([
                'user',
                'room',
                'roomType',
                'payment'
            ])
            ->first();

        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found.'
            ], 404);
        }

        return response()->json([
            'result' => true,
            'data' => $booking
        ]);
    }


    public function updateBookingStatus(Request $request, $id)
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $booking = Booking::where('id', $id)
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found.'
            ], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,cancelled,completed'
        ]);

        $booking->update([
            'status' => $validated['status']
        ]);

        return response()->json([
            'result' => true,
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
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

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
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $data = Booking::where('hotel_id', $hotel->id)
            ->where('status', 'completed')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'result' => true,
            'data' => [
                'hotel' => $hotel->name,
                'revenue' => $data
            ]
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
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $totalRooms = Room::where('hotel_id', $hotel->id)
            ->count();

        $occupiedRooms = Room::where('hotel_id', $hotel->id)
            ->where('status', 'occupied')
            ->count();

        $availableRooms = Room::where('hotel_id', $hotel->id)
            ->where('status', 'available')
            ->count();

        $occupancyRate = $totalRooms > 0
            ? round(($occupiedRooms / $totalRooms) * 100, 2)
            : 0;

        return response()->json([
            'result' => true,
            'data' => [
                'hotel' => $hotel->name,
                'total_rooms' => $totalRooms,
                'occupied_rooms' => $occupiedRooms,
                'available_rooms' => $availableRooms,
                'occupancy_rate' => $occupancyRate
            ]
        ]);
    }
}
