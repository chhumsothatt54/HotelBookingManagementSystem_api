<?php

namespace App\Http\Controllers;

use App\Models\RoomImage;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HotelManagerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Shared helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the authenticated manager's hotel or abort with a 404 that
     * matches the response shape the rest of this controller already uses.
     */
    protected function managerHotel(Request $request): Hotel
    {
        $hotel = Hotel::where('manager_id', $request->user()->id)->first();

        abort_unless($hotel, 404, 'Hotel not found.');

        return $hotel;
    }

    /**
     * Allowed booking status transitions. Anything not listed here as a key
     * is a terminal state and cannot be changed further.
     */
    protected const BOOKING_TRANSITIONS = [
        'pending' => ['approved', 'rejected'],
        'approved' => ['completed', 'cancelled'],
    ];

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    public function dashboard(Request $request)
    {
        $hotel = $this->managerHotel($request);

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
            ->where('status', 'checked_out')
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
                'rooms.amenities',

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


    public function createHotel(Request $request)
    {
        $manager = $request->user();

        // One manager = One hotel
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

            // ❌ REMOVE email from here

            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'province' => 'nullable|string|max:100',
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $validated['manager_id'] = $manager->id;

        // ✅ Hotel email = Manager account email
        $validated['email'] = $manager->email;

        $hotel = Hotel::create($validated);

        return response()->json([
            'result' => true,
            'message' => 'Hotel created successfully.',
            'data' => $hotel
        ], 201);
    }


    public function updateHotel(Request $request, $id)
    {
        $manager = $request->user();

        $hotel = Hotel::where('id', $id)
            ->where('manager_id', $manager->id)
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

            // ❌ REMOVE email validation

            'address' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:100',
            'country' => 'sometimes|required|string|max:100',
            'province' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // ✅ Always sync hotel email with manager account email
        $validated['email'] = $manager->email;

        $hotel->update($validated);

        return response()->json([
            'result' => true,
            'message' => 'Hotel updated successfully.',
            'data' => $hotel
        ]);
    }

    public function deleteHotel(Request $request, $id)
    {
        $hotel = Hotel::where('id', $id)
            ->where('manager_id', $request->user()->id)
            ->first();

        if (!$hotel) {
            return response()->json([
                'message' => 'Hotel not found.'
            ], 404);
        }

        $hasActiveBookings = Booking::where('hotel_id', $hotel->id)
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->exists();

        if ($hasActiveBookings) {
            return response()->json([
                'message' => 'This hotel has active bookings and cannot be deleted.'
            ], 409);
        }

        // We can assume cascade delete is set on DB level for relations like rooms, room_types, hotel_images, etc. 
        // If not, we might need to delete them explicitly. But it's usually better handled by migrations.
        $hotel->delete();

        return response()->json([
            'result' => true,
            'message' => 'Hotel deleted successfully.'
        ]);
    }


    public function hotelImages(Request $request)
    {
        $hotel = $this->managerHotel($request);

        $images = HotelImage::where('hotel_id', $hotel->id)
            ->latest()
            ->get()
            ->map(function ($image) {
                return [
                    'id' => $image->id,
                    'hotel_id' => $image->hotel_id,
                    'image' => $image->image_path,
                    'is_primary' => $image->is_primary,
                    'url' => asset('storage/' . $image->image),
                ];
            });

        return response()->json([
            'result' => true,
            'data' => $images,
        ]);
    }
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'avatar' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user->name = $request->name;

if ($request->filled('email')) {
    $user->email = $request->email;
}

$user->phone = $request->phone;

        if ($request->hasFile('avatar')) {

            // Delete old avatar
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Save new avatar
            $path = $request->file('avatar')->store(
                'avatars',
                'public'
            );

            $user->avatar = $path;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user,
        ]);
    }


    public function uploadImage(Request $request)
    {
        $hotel = $this->managerHotel($request);

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
            'image' => $path,
            'is_primary' => $isPrimary,
        ]);

        return response()->json([
            'result' => true,
            'message' => 'Hotel image uploaded successfully.',
            'data' => [
                'id' => $hotelImage->id,
                'hotel_id' => $hotelImage->hotel_id,
                'image_path' => $hotelImage->image,
                'is_primary' => $hotelImage->is_primary,
                'url' => asset('storage/' . $path),
            ]
        ], 201);
    }



    public function deleteImage(Request $request, $id)
    {
        $hotel = $this->managerHotel($request);

        $hotelImage = HotelImage::where('id', $id)
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$hotelImage) {
            return response()->json([
                'message' => 'Hotel image not found.'
            ], 404);
        }

        // Delete the physical image from storage
        if (!empty($hotelImage->image)) {
            Storage::disk('public')->delete($hotelImage->image);
        }

        // Delete database record
        $hotelImage->delete();

        return response()->json([
            'result' => true,
            'message' => 'Hotel image deleted successfully.'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Room Types
    |--------------------------------------------------------------------------
    */

    public function roomTypes(Request $request)
    {
        $hotel = $this->managerHotel($request);

        $roomTypes = RoomType::where('hotel_id', $hotel->id)
            ->with('rooms')
            ->paginate(15);

        return response()->json([
            'result' => true,
            'data' => $roomTypes
        ]);
    }


    public function storeRoomType(Request $request)
    {
        $hotel = $this->managerHotel($request);

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'max_guests'      => 'required|integer|min:1',       // កែពី capacity -> max_guests
            'price_per_night' => 'required|numeric|min:0',       // កែពី price -> price_per_night
            'status'          => 'nullable|string',
        ]);

        $validated['hotel_id'] = $hotel->id;

        $roomType = RoomType::create($validated);

        return response()->json([
            'result'  => true,
            'message' => 'Room type created successfully.',
            'data'    => $roomType
        ], 201);
    }

    public function updateRoomType(Request $request, $id)
    {
        $hotel = $this->managerHotel($request);

        $roomType = RoomType::where('id', $id)
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$roomType) {
            return response()->json([
                'message' => 'Room type not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name'            => 'sometimes|required|string|max:255',
            'description'     => 'nullable|string',
            'max_guests'      => 'sometimes|required|integer|min:1',
            'price_per_night' => 'sometimes|required|numeric|min:0',
            'status'          => 'sometimes|nullable|string',
        ]);

        $roomType->update($validated);

        return response()->json([
            'result'  => true,
            'message' => 'Room type updated successfully.',
            'data'    => $roomType
        ]);
    }


    public function deleteRoomType(Request $request, $id)
    {
        $hotel = $this->managerHotel($request);

        // លុប dd(...) ចេញ

        $roomType = RoomType::where('id', $id)
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$roomType) {
            return response()->json([
                'message' => 'Room type not found.'
            ], 404);
        }

        // ពិនិត្យមើលថាតើមានការកក់ (Booking) សកម្មដែរឬទេ
        $hasBookings = Booking::whereHas('room', function ($query) use ($roomType) {
            $query->where('room_type_id', $roomType->id);
        })
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->exists();

        if ($hasBookings) {
            return response()->json([
                'message' => 'Cannot delete this room type because it has active bookings.'
            ], 422);
        }

        // លុបបន្ទប់ទាំងឡាយណាដែលស្ថិតនៅក្នុង Room Type នេះ
        Room::where('room_type_id', $roomType->id)
            ->where('hotel_id', $hotel->id)
            ->delete();

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
        $hotel = $this->managerHotel($request);

        $rooms = Room::where('hotel_id', $hotel->id)
            ->with('roomType', 'amenities')
            ->paginate(15);

        return response()->json([
            'result' => true,
            'data' => $rooms
        ]);
    }


    public function storeRoom(Request $request)
    {
        $hotel = $this->managerHotel($request);

        $validated = $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'room_number' => 'required|string|max:50',
            'floor' => 'nullable|integer',
            'status' => 'required|in:available,maintenance,inactive',
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

        /*
    |--------------------------------------------------------------------------
    | Check duplicate room number in this hotel
    |--------------------------------------------------------------------------
    */

        $existingRoom = Room::where('hotel_id', $hotel->id)
            ->where('room_number', $validated['room_number'])
            ->exists();

        if ($existingRoom) {
            return response()->json([
                'message' => 'Room number ' . $validated['room_number'] . ' already exists in your hotel.'
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Create room
    |--------------------------------------------------------------------------
    */

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
        $hotel = $this->managerHotel($request);

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
            'status' => 'sometimes|required|in:available,maintenance,inactive',
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

        /*
    |--------------------------------------------------------------------------
    | Check duplicate room number
    |--------------------------------------------------------------------------
    */

        if (isset($validated['room_number'])) {

            $existingRoom = Room::where('hotel_id', $hotel->id)
                ->where('room_number', $validated['room_number'])
                ->where('id', '!=', $room->id)
                ->exists();

            if ($existingRoom) {
                return response()->json([
                    'message' => 'Room number ' . $validated['room_number'] . ' already exists in your hotel.'
                ], 422);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Update room
    |--------------------------------------------------------------------------
    */

        $room->update($validated);

        return response()->json([
            'result' => true,
            'message' => 'Room updated successfully.',
            'data' => $room
        ]);
    }


    public function deleteRoom(Request $request, $id)
    {
        $hotel = $this->managerHotel($request);

        $room = Room::where('id', $id)
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$room) {
            return response()->json([
                'message' => 'Room not found.'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Guard: block deletion while bookings against this specific room are
        | still active, so we never orphan a booking's room.
        |--------------------------------------------------------------------------
        */

        $hasActiveBookings = Booking::where('room_id', $room->id)
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->exists();

        if ($hasActiveBookings) {
            return response()->json([
                'message' => 'This room has active bookings and cannot be deleted.'
            ], 409);
        }

        $room->delete();

        return response()->json([
            'result' => true,
            'message' => 'Room deleted successfully.'
        ]);
    }

    public function roomImages(Request $request, $roomTypeId)
    {
        $hotel = $this->managerHotel($request);

        $roomType = RoomType::where('id', $roomTypeId)
            ->where('hotel_id', $hotel->id)
            ->firstOrFail();

        $images = RoomImage::where('room_type_id', $roomType->id)
            ->latest()
            ->get()
            ->map(function ($image) {
                return [
                    'id' => $image->id,
                    'room_type_id' => $image->room_type_id,
                    'url' => asset('storage/' . $image->image),
                    'is_primary' => $image->is_primary,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $images,
        ]);
    }

    public function uploadRoomImages(Request $request, $roomTypeId)
    {
        $hotel = $this->managerHotel($request);

        $roomType = RoomType::where('id', $roomTypeId)
            ->where('hotel_id', $hotel->id)
            ->firstOrFail();

        $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $images = [];

        foreach ($request->file('images') as $file) {

            $path = $file->store('room-images', 'public');

            $image = RoomImage::create([
                'room_type_id' => $roomType->id,
                'image' => $path,
                'is_primary' => !RoomImage::where('room_type_id', $roomType->id)->exists(),
            ]);

            $images[] = [
                'id' => $image->id,
                'room_type_id' => $image->room_type_id,
                'url' => asset('storage/' . $image->image),
                'is_primary' => $image->is_primary,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Room images uploaded successfully.',
            'data' => $images,
        ], 201);
    }

    public function deleteRoomImage(Request $request, $imageId)
    {
        $hotel = $this->managerHotel($request);

        $image = RoomImage::whereHas('roomType', function ($query) use ($hotel) {
            $query->where('hotel_id', $hotel->id);
        })->findOrFail($imageId);

        if (Storage::disk('public')->exists($image->image)) {
            Storage::disk('public')->delete($image->image);
        }

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room image deleted successfully.',
        ]);
    }

    //store amenity
    public function storeAmenity(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:amenities,name',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
        ]);

        $amenity = Amenity::create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'result' => true,
            'message' => 'Amenity created successfully.',
            'data' => $amenity,
        ], 201);
    }

    //updaste 

    public function updateAmenity(Request $request, $id)
    {
        $amenity = Amenity::find($id);

        if (!$amenity) {
            return response()->json([
                'result' => false,
                'message' => 'Amenity not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:amenities,name,' . $amenity->id,
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        $amenity->update([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? $amenity->status,
        ]);

        return response()->json([
            'result' => true,
            'message' => 'Amenity updated successfully.',
            'data' => $amenity,
        ]);
    }

    // delete amenity
    public function deleteAmenity(Request $request, $id)
    {
        $amenity = Amenity::find($id);

        if (!$amenity) {
            return response()->json([
                'result' => false,
                'message' => 'Amenity not found.'
            ], 404);
        }

        $amenity->delete();

        return response()->json([
            'result' => true,
            'message' => 'Amenity deleted successfully.',
        ]);
    }


    public function amenities(Request $request)
    {
        $hotel = $this->managerHotel($request);

        $rooms = Room::where('hotel_id', $hotel->id)
            ->with('amenities')
            ->paginate(15);

        $amenities = Amenity::orderBy('name')->get();

        return response()->json([
            'result' => true,
            'data' => [
                'rooms' => $rooms,
                'amenities' => $amenities,
            ]
        ]);
    }


    public function attachAmenity(Request $request, $roomId, $amenityId)
    {
        $hotel = $this->managerHotel($request);

        /*
    |--------------------------------------------------------------------------
    | Make sure the room belongs to this manager's hotel
    |--------------------------------------------------------------------------
    */

        $room = Room::where('id', $roomId)
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$room) {
            return response()->json([
                'result' => false,
                'message' => 'Room not found.'
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Make sure the amenity exists
    |--------------------------------------------------------------------------
    */

        $amenity = Amenity::where('id', $amenityId)
    ->where('status', 'active')
    ->first();

        if (!$amenity) {
    return response()->json([
        'result' => false,
        'message' => 'Amenity not found or is inactive.'
    ], 404);
}

        /*
    |--------------------------------------------------------------------------
    | Attach amenity to room
    |--------------------------------------------------------------------------
    */

        $room->amenities()->syncWithoutDetaching([
            $amenity->id
        ]);

        return response()->json([
            'result' => true,
            'message' => 'Amenity attached to room successfully.',
            'data' => [
                'room_id' => $room->id,
                'amenity_id' => $amenity->id
            ]
        ]);
    }


    public function detachAmenity(Request $request, $roomId, $amenityId)
    {
        $hotel = $this->managerHotel($request);

        /*
    |--------------------------------------------------------------------------
    | Make sure the room belongs to this manager's hotel
    |--------------------------------------------------------------------------
    */

        $room = Room::where('id', $roomId)
            ->where('hotel_id', $hotel->id)
            ->first();

        if (!$room) {
            return response()->json([
                'result' => false,
                'message' => 'Room not found.'
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Make sure the amenity exists
    |--------------------------------------------------------------------------
    */

        $amenity = Amenity::find($amenityId);

        if (!$amenity) {
            return response()->json([
                'message' => 'Amenity not found.'
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Detach amenity from room
    |--------------------------------------------------------------------------
    */

        $room->amenities()->detach($amenity->id);

        return response()->json([
            'result' => true,
            'message' => 'Amenity detached from room successfully.',
            'data' => [
                'room_id' => $room->id,
                'amenity_id' => $amenity->id
            ]
        ]);
    }







    public function showBooking(Request $request, $id)
    {
        $hotel = $this->managerHotel($request);

        $booking = Booking::where('id', $id)
            ->where('hotel_id', $hotel->id)
            ->with([
                'customer',
                'room.roomType',
                'payments'
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
        $hotel = $this->managerHotel($request);

        $booking = Booking::where('hotel_id', $hotel->id)
            ->with('room')
            ->findOrFail($id);

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:pending,confirmed,checked_in,checked_out,cancelled,rejected'
            ],
        ]);

        $newStatus = $validated['status'];
        $currentStatus = $booking->status;

        /*
    |--------------------------------------------------------------------------
    | Allowed Status Transitions
    |--------------------------------------------------------------------------
    */

        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled', 'rejected'],
            'confirmed' => ['checked_in', 'cancelled'],
            'checked_in' => ['checked_out', 'cancelled'],
            'checked_out' => [],
            'cancelled' => [],
            'rejected' => [],
        ];

        if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            return response()->json([
                'message' => "Cannot change booking status from {$currentStatus} to {$newStatus}."
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Update Booking
    |--------------------------------------------------------------------------
    */

        $booking->update([
            'status' => $newStatus,
        ]);

        /*
    |--------------------------------------------------------------------------
    | Update Room Status
    |--------------------------------------------------------------------------
    |
    | checked_in  → room inactive
    | checked_out → room maintenance
    | cancelled/rejected → room available
    |
    */

        if ($booking->room) {

            if ($newStatus === 'checked_in') {
                $booking->room->update([
                    'status' => 'inactive',
                ]);
            }

            if ($newStatus === 'checked_out') {
                $booking->room->update([
                    'status' => 'maintenance',
                ]);
            }

            if (in_array($newStatus, ['cancelled', 'rejected'], true)) {
                $booking->room->update([
                    'status' => 'available',
                ]);
            }
        }

        return response()->json([
            'result' => true,
            'message' => 'Booking status updated successfully.',
            'data' => $booking->fresh('room'),
        ]);
    }




    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */

    public function reviews(Request $request)
    {
        $hotel = $this->managerHotel($request);

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
        $hotel = $this->managerHotel($request);

        $bookings = Booking::where('hotel_id', $hotel->id)
            ->where('status', 'checked_out')
            ->select('check_out', 'total_amount')
            ->get();

        $data = $bookings
            ->groupBy(fn($booking) => Carbon::parse($booking->check_out)->toDateString())
            ->map(fn($group, $date) => [
                'date' => $date,
                'revenue' => $group->sum('total_amount'),
            ])
            ->sortKeys()
            ->values();

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
        $hotel = $this->managerHotel($request);

        $totalRooms = Room::where('hotel_id', $hotel->id)
            ->count();

        $occupiedRooms = Room::where('hotel_id', $hotel->id)
            ->where('status', 'inactive')
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
    //payment 
    public function bookings(Request $request)
    {
        $hotel = $this->managerHotel($request);

        $bookings = Booking::where('hotel_id', $hotel->id)
            ->with([
                'customer',
                'room.roomType',
                'payments'
            ])
            ->latest()
            ->get();

        return response()->json([
            'result' => true,
            'data' => $bookings
        ]);
    }


}
