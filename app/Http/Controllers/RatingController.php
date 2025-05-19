<?php

namespace App\Http\Controllers;

use App\Http\Resources\RatingResource;
use App\Models\Booking;
use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    /**
     * Store a newly created rating in storage.
     */
    public function store(Request $req)
    {
        $user = $req->user('sanctum');

        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized',
                'data' => null
            ], 401);
        }

        // Validate request
        $req->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:500'],
        ]);

        // Check if booking belongs to user
        $booking = Booking::find($req->input('booking_id'));

        if (!$booking || $booking->user_id !== $user->id) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized to rate this booking',
                'data' => null
            ], 403);
        }

        // Check if booking is completed
        if ($booking->booking_status !== 'completed') {
            return response()->json([
                'result' => false,
                'message' => 'Can only rate completed bookings',
                'data' => null
            ], 422);
        }

        // Check if user has already rated this booking
        $existingRating = Rating::where('guest_id', $user->id)
            ->where('booking_id', $booking->id)
            ->first();

        if ($existingRating) {
            return response()->json([
                'result' => false,
                'message' => 'You have already rated this booking',
                'data' => null
            ], 422);
        }

        // Create rating
        $rating = new Rating();
        $rating->guest_id = $user->id;
        $rating->booking_id = $booking->id;
        $rating->rating = $req->input('rating');
        $rating->comment = $req->input('comment');
        $rating->save();

        // Return response
        return response()->json([
            'result' => true,
            'message' => 'Rating submitted successfully',
            'data' => new RatingResource($rating)
        ]);
    }
    public function update(Request $req, $id)
    {
        $user = $req->user('sanctum');

        // Validate
        $req->merge(['id' => $id]);
        $req->validate([
            'id' => ['required', 'integer', 'min:1', 'exists:ratings,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:500'],
        ]);

        // Get rating
        $rating = Rating::find($id);

        // If rating not found
        if (!$rating) {
            return response()->json([
                'result' => false,
                'message' => 'Rating not found',
                'data' => null
            ], 404);
        }

        // Check if rating belongs to user
        if ($rating->guest_id !== $user->id) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized to update this rating',
                'data' => null
            ], 403);
        }

        // Update rating
        $rating->rating = $req->input('rating');
        $rating->comment = $req->input('comment');
        $rating->save();

        // Return response
        return response()->json([
            'result' => true,
            'message' => 'Rating updated successfully',
            'data' => new RatingResource($rating)
        ]);
    }
    public function destroy(Request $req, $id)
    {
        $user = $req->user('sanctum');

        // Validate
        $req->merge(['id' => $id]);
        $req->validate([
            'id' => ['required', 'integer', 'min:1', 'exists:ratings,id']
        ]);

        // Get rating
        $rating = Rating::find($id);

        // If rating not found
        if (!$rating) {
            return response()->json([
                'result' => false,
                'message' => 'Rating not found',
                'data' => null
            ], 404);
        }

        // Check if rating belongs to user or user is admin/superadmin
        // Regular users (role_id = 1) can only delete their own ratings
        // Admins (role_id = 2) and Super Admins (role_id = 3) can delete all ratings
        if ($rating->guest_id !== $user->id && $user->role_id == 1) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized to delete this rating',
                'data' => null
            ], 403);
        }

        // Delete rating
        $rating->delete();

        // Return response
        return response()->json([
            'result' => true,
            'message' => 'Rating deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Get ratings for a specific room.
     */
    public function getRoomRatings(Request $req, $roomId)
    {
        // Validate
        $req->merge(['room_id' => $roomId]);
        $req->validate([
            'room_id' => ['required', 'integer', 'min:1', 'exists:rooms,id'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $per_page = $req->filled('per_page') ? intval($req->input('per_page')) : 10;

        // Get ratings for the room
        $ratings = Rating::whereHas('booking.bookingRooms', function($query) use ($roomId) {
            $query->where('room_id', $roomId);
        })
        ->with('user:id,username')
        ->orderBy('created_at', 'desc')
        ->paginate($per_page);

        return $this->res_paginate($ratings, 'Room ratings retrieved successfully', RatingResource::collection($ratings));
    }
}
