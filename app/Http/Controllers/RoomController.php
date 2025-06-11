<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    public function index(Request $req)
    {
        // Validate request parameters
        $req->validate([
            'scol' => ['nullable', 'string', 'in:id,room_number,price'],
            'sdir' => ['nullable', 'string', 'in:asc,desc'],
            'search' => ['nullable', 'string', 'max:50'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'check_in_date' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out_date' => ['nullable', 'date', 'after:check_in_date'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // Set up default values
        $scol = $req->filled('scol') ? $req->input('scol') : 'room_number';
        $sdir = $req->filled('sdir') ? $req->input('sdir') : 'asc';
        $per_page = $req->filled('per_page') ? intval($req->input('per_page')) : 10;

        // Start building the query
        $rooms = Room::with(['roomType']);

        // Search functionality
        if ($req->filled('search')) {
            $search = $req->input('search');
            $rooms->where(function($query) use ($search) {
                $query->where('room_number', 'like', '%' . $search . '%')
                      ->orWhere('desc', 'like', '%' . $search . '%')
                      ->orWhereHas('roomType', function($q) use ($search) {
                          $q->where('type', 'like', '%' . $search . '%');
                      });
            });
        }

        // Filter by room type
        if ($req->filled('room_type_id')) {
            $rooms->where('room_type_id', $req->input('room_type_id'));
        }

        // Filter by price range
        if ($req->filled('min_price') || $req->filled('max_price')) {
            $rooms->whereHas('roomType', function($query) use ($req) {
                if ($req->filled('min_price')) {
                    $query->where('price', '>=', $req->input('min_price'));
                }
                if ($req->filled('max_price')) {
                    $query->where('price', '<=', $req->input('max_price'));
                }
            });
        }

        // Filter by availability (check if room is not booked for the given dates)
        if ($req->filled('check_in_date') && $req->filled('check_out_date')) {
            $checkInDate = $req->input('check_in_date');
            $checkOutDate = $req->input('check_out_date');

            $rooms->whereDoesntHave('bookingRooms', function($query) use ($checkInDate, $checkOutDate) {
                $query->whereHas('booking', function($q) use ($checkInDate, $checkOutDate) {
                    $q->where(function($innerQuery) use ($checkInDate, $checkOutDate) {
                        // Check if there's any overlap with existing bookings
                        $innerQuery->where(function($dateQuery) use ($checkInDate, $checkOutDate) {
                            $dateQuery->where('check_in_date', '<=', $checkOutDate)
                                     ->where('check_out_date', '>=', $checkInDate);
                        });
                    })
                    ->where('booking_status', '!=', 'cancelled');
                });
            });
        }

        // Apply sorting and pagination
        $result = $rooms->orderBy($scol, $sdir)->paginate($per_page);

        // Return paginated response
        return $this->res_paginate($result, 'Rooms retrieved successfully', RoomResource::collection($result));
    }

    /**
     * Store a newly created room in storage.
     * Only accessible by admin and superadmin.
     */
    public function store(Request $req)
    {
        // Check if user is admin or super admin
        $user = $req->user('sanctum');
        // if (!$user || !$user->IsAdmin()) {
        //     return response()->json([
        //         'result' => false,
        //         'message' => 'Unauthorized. Only admin and super admin can create rooms.',
        //         'data' => null
        //     ], 403);
        // }

        // Validate request
        $req->validate([
            'room_number' => ['required', 'string', 'max:10', 'unique:rooms,room_number'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'desc' => ['required', 'string'],
        ]);

        // Get image from room type
        $roomType = \App\Models\RoomType::find($req->input('room_type_id'));
        $thumbnail = $roomType ? $roomType->image : 'no_image.jpg';

        // Create new room
        $room = new Room();
        $room->room_number = $req->input('room_number');
        $room->room_type_id = $req->input('room_type_id');
        $room->desc = $req->input('desc');
        $room->room_image = $thumbnail;
        $room->save();

        // Refresh room with its type and images
        $room = Room::with(['roomType', 'images'])->find($room->id);

        return response()->json([
            'result' => true,
            'message' => 'Room created successfully',
            'data' => new RoomResource($room)
        ]);
    }

    /**
     * Display the specified room.
     */
    public function show(Request $req, $id)
    {
        // Validate
        $req->merge(['id' => $id]);
        $req->validate([
            'id' => ['required', 'integer', 'min:1', 'exists:rooms,id']
        ]);

        // Get room with relationships
        $room = Room::with([
            'roomType',
            'images',
            'ratings' => function($query) {
                $query->with('user:id,username');
            }
        ])->find($id);

        // If room not found
        if (!$room) {
            return response()->json([
                'result' => false,
                'message' => 'Room not found',
                'data' => null
            ], 404);
        }

        // Return response
        return response()->json([
            'result' => true,
            'message' => 'Room details retrieved successfully',
            'data' => new RoomResource($room)
        ]);
    }

//update room type by id and only role id 2 as admin
public function update(Request $req, $id)
{
    // Check if user is admin (role_id == 2)
    $user = $req->user('sanctum');
    if (!$user || $user->role_id != 2) {
        return response()->json([
            'result' => false,
            'message' => 'Unauthorized. Only admin can update rooms.',
            'data' => null
        ], 403);
    }

    // Validate request
    $req->merge(['id' => $id]);
    $req->validate([
        'id' => ['required', 'integer', 'min:1', 'exists:rooms,id'],
        'room_number' => ['required', 'string', 'max:10', 'unique:rooms,room_number,' . $id],
        'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
        'desc' => ['required', 'string'],
        'is_active' => ['required', 'boolean'],
    ]);

    // Find the room
    $room = Room::find($id);
    if (!$room) {
        return response()->json([
            'result' => false,
            'message' => 'Room not found.',
            'data' => null
        ], 404);
    }

    // Update room data
    $room->room_number = $req->input('room_number');
    $room->room_type_id = $req->input('room_type_id');
    $room->desc = $req->input('desc');
    $room->is_active = $req->input('is_active');

    // Optionally update thumbnail from room type
    $roomType = RoomType::find($req->input('room_type_id'));
    if ($roomType && $roomType->image) {
        $room->room_image = $roomType->image;
    }

    $room->save();

    // Refresh and return updated room
    $room = Room::with(['roomType', 'images'])->find($room->id);

    return response()->json([
        'result' => true,
        'message' => 'Room updated successfully',
        'data' => new RoomResource($room)
    ]);
}


    /**
     * Remove the specified room from storage.
     * Only accessible by admin (role_id = 2).
     */
    public function destroy(Request $req, $id)
    {
        // Check if user is admin (role_id == 2)
        $user = $req->user('sanctum');
        if (!$user || $user->role_id != 2) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized. Only admin can delete rooms.',
                'data' => null
            ], 403);
        }

        // Validate
        $req->merge(['id' => $id]);
        $req->validate([
            'id' => ['required', 'integer', 'min:1', 'exists:rooms,id']
        ]);

        // Get room with images
        $room = Room::with('images')->find($id);

        // If room not found
        if (!$room) {
            return response()->json([
                'result' => false,
                'message' => 'Room not found',
                'data' => null
            ], 404);
        }

        // Check if room has active bookings
        $hasActiveBookings = $room->bookingRooms()
            ->whereHas('booking', function($query) {
                $query->where('booking_status', '!=', 'cancelled')
                      ->where('check_out_date', '>=', now());
            })
            ->exists();

        if ($hasActiveBookings) {
            return response()->json([
                'result' => false,
                'message' => 'Cannot delete room with active bookings',
                'data' => null
            ], 422);
        }

        // Delete room thumbnail
        if ($room->room_image !== 'no_image.jpg') {
            Storage::disk('public')->delete($room->room_image);
        }

        // Delete all room images
        foreach ($room->images as $image) {
            Storage::disk('public')->delete($image->image_url);
            $image->delete();
        }

        // Delete room
        $room->delete();

        return response()->json([
            'result' => true,
            'message' => 'Room deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Get room types for filtering.
     */
    public function getRoomTypes()
    {
        $roomTypes = RoomType::all();

        return response()->json([
            'result' => true,
            'message' => 'Room types retrieved successfully',
            'data' => $roomTypes
        ]);
    }

    //get all rooms
    public function rooms(Request $req)
    {
        $rooms = Room::with(['roomType', 'images'])->get();

        return response()->json([
            'result' => true,
            'message' => 'Rooms retrieved successfully',
            'data' => RoomResource::collection($rooms)
        ]);
    }
}
