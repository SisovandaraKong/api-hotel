<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Display a listing of the user's bookings.
     */
    public function index(Request $req){
        $user = $req->user('sanctum');

        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized',
                'data' => null
            ], 401);
        }

        // Validate
        $req->validate([
            'status' => ['nullable', 'string', 'in:pending,confirmed,cancelled,completed'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $per_page = $req->filled('per_page') ? intval($req->input('per_page')) : 10;

        // Get bookings for the authenticated user
        $bookings = Booking::where('user_id', $user->id);

        // Filter by status if provided
        if ($req->filled('status')) {
            $bookings->where('booking_status', $req->input('status'));
        }

        // Get bookings with relationships
        $result = $bookings->with([
            'bookingRooms.room.roomType',
            'payment'
        ])
        ->orderBy('created_at', 'desc')
        ->paginate($per_page);

        return $this->res_paginate($result, 'Bookings retrieved successfully', BookingResource::collection($result));
    }

    /**
     * Store a newly created booking in storage.
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
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'payment_method' => ['required', 'string', 'in:credit_card,paypal,cash'],
            'total_payment' => ['required', 'numeric', 'min:0'],
        ]);

        // Check if rooms are available for the selected dates
        $checkInDate = $req->input('check_in_date');
        $checkOutDate = $req->input('check_out_date');
        $roomIds = $req->input('room_ids');

        // Check room availability
        $unavailableRooms = Room::whereIn('id', $roomIds)
            ->whereHas('bookingRooms', function($query) use ($checkInDate, $checkOutDate) {
                $query->whereHas('booking', function($q) use ($checkInDate, $checkOutDate) {
                    $q->where(function($innerQuery) use ($checkInDate, $checkOutDate) {
                        $innerQuery->where('check_in_date', '<=', $checkOutDate)
                                  ->where('check_out_date', '>=', $checkInDate);
                    })
                    ->where('booking_status', '!=', 'cancelled');
                });
            })
            ->get();

        if ($unavailableRooms->count() > 0) {
            return response()->json([
                'result' => false,
                'message' => 'Some rooms are not available for the selected dates',
                'data' => [
                    'unavailable_rooms' => $unavailableRooms->pluck('room_number')
                ]
            ], 422);
        }

        // Start transaction
        DB::beginTransaction();

        try {
            // Create booking
            $booking = new Booking();
            $booking->user_id = $user->id;
            $booking->booking_status = 'pending';
            $booking->check_in_date = $checkInDate;
            $booking->check_out_date = $checkOutDate;
            $booking->save();

            // Create booking rooms
            foreach ($roomIds as $roomId) {
                $bookingRoom = new BookingRoom();
                $bookingRoom->booking_id = $booking->id;
                $bookingRoom->room_id = $roomId;
                $bookingRoom->save();
            }

            // Create payment
            $payment = new Payment();
            $payment->booking_id = $booking->id;
            $payment->total_payment = $req->input('total_payment');
            $payment->method_payment = $req->input('payment_method');
            $payment->date_payment = now();
            $payment->save();

            // Commit transaction
            DB::commit();

            // Refresh booking with relationships
            $booking = Booking::with([
                'bookingRooms.room.roomType',
                'payment'
            ])->find($booking->id);

            return response()->json([
                'result' => true,
                'message' => 'Booking created successfully',
                'data' => new BookingResource($booking)
            ]);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'result' => false,
                'message' => 'Failed to create booking: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Display the specified booking.
     */
    public function show(Request $req, $id)
    {
        $user = $req->user('sanctum');

        // Validate
        $req->merge(['id' => $id]);
        $req->validate([
            'id' => ['required', 'integer', 'min:1', 'exists:bookings,id']
        ]);

        // Get booking with relationships
        $booking = Booking::with([
            'bookingRooms.room.roomType',
            'payment',
            'user:id,name,email'
        ])->find($id);

        // If booking not found
        if (!$booking) {
            return response()->json([
                'result' => false,
                'message' => 'Booking not found',
                'data' => null
            ], 404);
        }

        // Check if user is authorized to view this booking
        // Regular users (role_id = 1) can only view their own bookings
        // Admins (role_id = 2) and Super Admins (role_id = 3) can view all bookings
        if ($user->role_id == 1 && $booking->user_id !== $user->id) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized to view this booking',
                'data' => null
            ], 403);
        }

        // Return response
        return response()->json([
            'result' => true,
            'message' => 'Booking details retrieved successfully',
            'data' => new BookingResource($booking)
        ]);
    }

    /**
     * Cancel the specified booking.
     */
    public function cancel(Request $req, $id)
    {
        $user = $req->user('sanctum');

        // Validate
        $req->merge(['id' => $id]);
        $req->validate([
            'id' => ['required', 'integer', 'min:1', 'exists:bookings,id'],
            'cancellation_reason' => ['nullable', 'string', 'max:255']
        ]);

        // Get booking
        $booking = Booking::find($id);

        // If booking not found
        if (!$booking) {
            return response()->json([
                'result' => false,
                'message' => 'Booking not found',
                'data' => null
            ], 404);
        }

        // Check if user is authorized to cancel this booking
        // Regular users (role_id = 1) can only cancel their own bookings
        // Admins (role_id = 2) and Super Admins (role_id = 3) can cancel all bookings
        if ($user->role_id == 1 && $booking->user_id !== $user->id) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized to cancel this booking',
                'data' => null
            ], 403);
        }

        // Check if booking can be cancelled
        if ($booking->booking_status === 'cancelled') {
            return response()->json([
                'result' => false,
                'message' => 'Booking is already cancelled',
                'data' => null
            ], 422);
        }

        if ($booking->booking_status === 'completed') {
            return response()->json([
                'result' => false,
                'message' => 'Cannot cancel a completed booking',
                'data' => null
            ], 422);
        }

        // Check cancellation policy
        $checkInDate = new \DateTime($booking->check_in_date);
        $now = new \DateTime();
        $daysDifference = $now->diff($checkInDate)->days;

        // If check-in date is less than 24 hours away, don't allow cancellation
        if ($daysDifference < 1) {
            return response()->json([
                'result' => false,
                'message' => 'Cannot cancel booking less than 24 hours before check-in',
                'data' => [
                    'cancellation_policy' => 'Bookings can only be cancelled at least 24 hours before check-in'
                ]
            ], 422);
        }

        // Cancel booking
        $booking->booking_status = 'cancelled';
        $booking->cancellation_reason = $req->input('cancellation_reason');
        $booking->save();

        // Refresh booking with relationships
        $booking = Booking::with([
            'bookingRooms.room.roomType',
            'payment'
        ])->find($id);

        return response()->json([
            'result' => true,
            'message' => 'Booking cancelled successfully',
            'data' => new BookingResource($booking)
        ]);
    }

    /**
     * Get cancellation policy.
     */
    public function getCancellationPolicy()
    {
        return response()->json([
            'result' => true,
            'message' => 'Cancellation policy retrieved successfully',
            'data' => [
                'policy' => 'Bookings can be cancelled free of charge at least 24 hours before the check-in date. Cancellations made less than 24 hours before check-in are not refundable.',
                'terms' => [
                    'Cancellations must be made at least 24 hours before the check-in date for a full refund.',
                    'Cancellations made less than 24 hours before check-in are not eligible for a refund.',
                    'No-shows will be charged the full amount of the booking.',
                    'Early departure will not result in a refund for unused nights.',
                    'All refunds will be processed within 7-14 business days.'
                ]
            ]
        ]);
    }

}
