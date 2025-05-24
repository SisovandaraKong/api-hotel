<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\BookingService;

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
     * Display the specified booking with status pending.
     */
    public function show(Request $req, $id)
    {
        $user = $req->user('sanctum');

        // Validate
        $req->merge(['id' => $id]);
        $req->validate([
            'id' => ['required', 'integer', 'min:1', 'exists:bookings,id']
        ]);

        // Get booking with relationships, only if status is pending
        $booking = Booking::with([
            'bookingRooms.room.roomType',
            'payment',
            'user:id,name,email'
        ])
        ->where('id', $id)
        ->where('booking_status', 'pending')
        ->first();

        // If booking not found or not pending
        if (!$booking) {
            return response()->json([
                'result' => false,
                'message' => 'Pending booking not found',
                'data' => null
            ], 404);
        }

        // Check if user is authorized to view this booking
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
            'message' => 'Pending booking details retrieved successfully',
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

        // Check cancellation policy (use hours for accuracy)
        $checkInDate = new \DateTime($booking->check_in_date);
        $now = new \DateTime();
        $interval = $now->diff($checkInDate);
        $hoursDifference = ($interval->days * 24) + $interval->h + ($interval->i > 0 ? 1 : 0);

        // If check-in date is less than 24 hours away, don't allow cancellation
        if ($hoursDifference < 24 && $checkInDate > $now) {
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

    // Update booking
    public function update(Request $req, $id)
    {
        $user = $req->user('sanctum');

        // Validate booking exists
        $req->merge(['id' => $id]);
        $req->validate([
            'id' => ['required', 'integer', 'min:1', 'exists:bookings,id'],
            'check_in_date' => ['sometimes', 'date', 'after_or_equal:today'],
            'check_out_date' => ['sometimes', 'date', 'after:check_in_date'],
            'room_ids' => ['sometimes', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'payment_method' => ['sometimes', 'string', 'in:credit_card,paypal,cash'],
            'total_payment' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $booking = Booking::with(['bookingRooms', 'payment'])->find($id);

        if (!$booking) {
            return response()->json([
                'result' => false,
                'message' => 'Booking not found',
                'data' => null
            ], 404);
        }

        // Only allow owner or admin/superadmin
        if ($user->role_id == 1 && $booking->user_id !== $user->id) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized to update this booking',
                'data' => null
            ], 403);
        }

        // Prevent update if cancelled or completed
        if (in_array($booking->booking_status, ['cancelled', 'completed'])) {
            return response()->json([
                'result' => false,
                'message' => 'Cannot update a cancelled or completed booking',
                'data' => null
            ], 422);
        }

        // Prepare new values
        $checkInDate = $req->input('check_in_date', $booking->check_in_date);
        $checkOutDate = $req->input('check_out_date', $booking->check_out_date);
        $roomIds = $req->input('room_ids', $booking->bookingRooms->pluck('room_id')->toArray());

        // Check room availability if dates or rooms are changed
        if (
            $req->has('check_in_date') || $req->has('check_out_date') || $req->has('room_ids')
        ) {
            $unavailableRooms = Room::whereIn('id', $roomIds)
                ->whereHas('bookingRooms', function($query) use ($checkInDate, $checkOutDate, $id) {
                    $query->whereHas('booking', function($q) use ($checkInDate, $checkOutDate, $id) {
                        $q->where(function($innerQuery) use ($checkInDate, $checkOutDate) {
                            $innerQuery->where('check_in_date', '<=', $checkOutDate)
                                       ->where('check_out_date', '>=', $checkInDate);
                        })
                        ->where('id', '!=', $id)
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
        }

        DB::beginTransaction();
        try {
            // Update booking dates
            $booking->check_in_date = $checkInDate;
            $booking->check_out_date = $checkOutDate;
            $booking->save();

            // Update rooms if changed
            if ($req->has('room_ids')) {
                // Remove old rooms
                BookingRoom::where('booking_id', $booking->id)->delete();
                // Add new rooms
                foreach ($roomIds as $roomId) {
                    $bookingRoom = new BookingRoom();
                    $bookingRoom->booking_id = $booking->id;
                    $bookingRoom->room_id = $roomId;
                    $bookingRoom->save();
                }
            }

            // Update payment if provided
            if ($req->has('payment_method') || $req->has('total_payment')) {
                $payment = $booking->payment;
                if ($payment) {
                    if ($req->has('payment_method')) {
                        $payment->method_payment = $req->input('payment_method');
                    }
                    if ($req->has('total_payment')) {
                        $payment->total_payment = $req->input('total_payment');
                    }
                    $payment->save();
                }
            }

            DB::commit();

            // Refresh booking with relationships
            $booking = Booking::with([
                'bookingRooms.room.roomType',
                'payment'
            ])->find($booking->id);

            return response()->json([
                'result' => true,
                'message' => 'Booking updated successfully',
                'data' => new BookingResource($booking)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'result' => false,
                'message' => 'Failed to update booking: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    // Create a booking service
    public function createBookingService(Request $req)
    {
        // Validate request
        $req->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'service_type_id' => ['required', 'integer', 'exists:service_types,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        // Create booking service
        $bookingService = new BookingService();
        $bookingService->booking_id = $req->input('booking_id');
        $bookingService->service_id = $req->input('service_id');
        $bookingService->service_type_id = $req->input('service_type_id');
        $bookingService->quantity = $req->input('quantity');
        $bookingService->price = $req->input('price');
        $bookingService->save();

        return response()->json([
            'result' => true,
            'message' => 'Booking service created successfully',
            'data' => $bookingService
        ]);
    }
    /**
     * Get booking services for a specific booking.
     */
    public function getBookingServices(Request $req, $bookingId)
    {
        $user = $req->user('sanctum');

        // Validate booking exists
        $req->merge(['booking_id' => $bookingId]);
        $req->validate([
            'booking_id' => ['required', 'integer', 'min:1', 'exists:bookings,id']
        ]);

        // Get booking services
        $bookingServices = BookingService::where('booking_id', $bookingId)
            ->with(['service', 'serviceType'])
            ->get();

        if ($bookingServices->isEmpty()) {
            return response()->json([
                'result' => false,
                'message' => 'No services found for this booking',
                'data' => null
            ], 404);
        }

        return response()->json([
            'result' => true,
            'message' => 'Booking services retrieved successfully',
            'data' => $bookingServices
        ]);
    }
    /**
     * Update a booking service.
     */
    public function updateBookingService(Request $req, $id)
    {
        $user = $req->user('sanctum');

        // Validate booking service exists
        $req->merge(['id' => $id]);
        $req->validate([
            'id' => ['required', 'integer', 'min:1', 'exists:booking_services,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'price' => ['sometimes', 'numeric', 'min:0'],
        ]);

        // Get booking service
        $bookingService = BookingService::find($id);

        if (!$bookingService) {
            return response()->json([
                'result' => false,
                'message' => 'Booking service not found',
                'data' => null
            ], 404);
        }

        // Only allow owner or admin/superadmin
        if ($user->role_id == 1 && $bookingService->booking->user_id !== $user->id) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized to update this booking service',
                'data' => null
            ], 403);
        }

        // Update fields if provided
        if ($req->has('quantity')) {
            $bookingService->quantity = $req->input('quantity');
        }
        if ($req->has('price')) {
            $bookingService->price = $req->input('price');
        }
        
        $bookingService->save();

        return response()->json([
            'result' => true,
            'message' => 'Booking service updated successfully',
            'data' => $bookingService
        ]);
    }
    /**
     * Delete a booking service.
     */
    public function deleteBookingService(Request $req, $id)
    {
        $user = $req->user('sanctum');

        // Validate booking service exists
        $req->merge(['id' => $id]);
        $req->validate([
            'id' => ['required', 'integer', 'min:1', 'exists:booking_services,id']
        ]);

        // Get booking service
        $bookingService = BookingService::find($id);

        if (!$bookingService) {
            return response()->json([
                'result' => false,
                'message' => 'Booking service not found',
                'data' => null
            ], 404);
        }

        // Only allow owner or admin/superadmin
        if ($user->role_id == 1 && $bookingService->booking->user_id !== $user->id) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized to delete this booking service',
                'data' => null
            ], 403);
        }

        // Delete booking service
        $bookingService->delete();

        return response()->json([
            'result' => true,
            'message' => 'Booking service deleted successfully',
            'data' => null
        ]);
    }
    
    // Get all booking services
    public function getAllBookingServices(Request $req)
    {
        $user = $req->user('sanctum');

        // Validate user role
        if (!$user || !in_array($user->role_id, [2, 3])) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized',
                'data' => null
            ], 401);
        }

        // Get all booking services with relationships
        $bookingServices = BookingService::with(['service', 'serviceType', 'booking.user'])
            ->get();

        return response()->json([
            'result' => true,
            'message' => 'Booking services retrieved successfully',
            'data' => $bookingServices
        ]);
    }

    

}
