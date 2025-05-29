<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\UserResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\BookingRoom; // Add this at the top
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\BookingServiceResource;
use App\Models\BookingService; // Add this at the top
use App\Models\Room;
use Illuminate\Support\Facades\DB;


class AdminController extends Controller
{
    /**
     * Get all bookings (admin only).
     */
    public function getAllBookings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,confirmed,cancelled,completed',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'per_page' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $per_page = $request->filled('per_page') ? intval($request->input('per_page')) : 10;

        $bookings = Booking::with([
            'user:id,name,email',
            'bookingRooms.room.roomType',
            'payment'
        ]);

        // Filter by status if provided
        if ($request->filled('status')) {
            $bookings->where('booking_status', $request->status);
        }

        // Filter by date range if provided
        if ($request->filled('from_date')) {
            $bookings->where('check_in_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $bookings->where('check_out_date', '<=', $request->to_date);
        }

        $result = $bookings->orderBy('created_at', 'desc')->paginate($per_page);

        return $this->res_paginate($result, 'Bookings retrieved successfully', BookingResource::collection($result));
    }

    /**
     * Update booking status (admin only).
     */
    public function updateBookingStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'booking_status' => 'required|string|in:pending,confirmed,cancelled,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'result' => false,
                'message' => 'Booking not found',
                'data' => null
            ], 404);
        }

        $booking->booking_status = $request->booking_status;
        $booking->save();

        return response()->json([
            'result' => true,
            'message' => 'Booking status updated successfully',
            'data' => new BookingResource($booking->load(['user:id,name,email', 'bookingRooms.room.roomType', 'payment']))
        ]);
    }

    /**
     * Get all users (admin only).
     */
    public function getAllUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'nullable|integer|exists:roles,id',
            'search' => 'nullable|string|max:50',
            'per_page' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $per_page = $request->filled('per_page') ? intval($request->input('per_page')) : 10;

        $users = User::with('role');

        // Filter by role if provided
        if ($request->filled('role_id')) {
            $users->where('role_id', $request->role_id);
        }

        // Search by name or email if provided
        if ($request->filled('search')) {
            $search = $request->search;
            $users->where(function($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $result = $users->orderBy('created_at', 'desc')->paginate($per_page);

        return $this->res_paginate($result, 'Users retrieved successfully', UserResource::collection($result));
    }

    /**
     * Create a new user (super admin only).
     */
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'gender' => 'nullable|string|in:male,female,other',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'gender' => $request->gender,
            'role_id' => $request->role_id,
        ]);

        return response()->json([
            'result' => true,
            'message' => 'User created successfully',
            'data' => new UserResource($user->load('role'))
        ]);
    }

    /**
     * Update user role (super admin only).
     */
    public function updateUserRole(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        // Super admin cannot change their own role
        if ($user->id === $request->user()->id) {
            return response()->json([
                'result' => false,
                'message' => 'Cannot change your own role',
                'data' => null
            ], 422);
        }

        $user->role_id = $request->role_id;
        $user->save();

        return response()->json([
            'result' => true,
            'message' => 'User role updated successfully',
            'data' => new UserResource($user->load('role'))
        ]);
    }

    /**
     * Delete a user (super admin only).
     */
    public function deleteUser(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        // Super admin cannot delete themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'result' => false,
                'message' => 'Cannot delete your own account',
                'data' => null
            ], 422);
        }

        // Delete user
        $user->delete();

        return response()->json([
            'result' => true,
            'message' => 'User deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Get all payments (admin only).
     */
    public function getAllPayments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_status' => 'nullable|string|in:pending,completed,failed,refunded',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'per_page' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $per_page = $request->filled('per_page') ? intval($request->input('per_page')) : 10;

        $payments = Payment::with(['booking.user:id,name,email']);

        // Filter by payment status if provided
        if ($request->filled('payment_status')) {
            $payments->where('payment_status', $request->payment_status);
        }

        // Filter by date range if provided
        if ($request->filled('from_date')) {
            $payments->where('date_payment', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $payments->where('date_payment', '<=', $request->to_date);
        }

        $result = $payments->orderBy('date_payment', 'desc')->paginate($per_page);

        return $this->res_paginate($result, 'Payments retrieved successfully', PaymentResource::collection($result));
        // return $this->res_paginate($result, 'Payments retrieved successfully');
    }

    /**
     * Update payment status (admin only).
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_status' => 'required|string|in:pending,completed,failed,refunded',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json([
                'result' => false,
                'message' => 'Payment not found',
                'data' => null
            ], 404);
        }

        $payment->payment_status = $request->payment_status;
        $payment->save();

        // If payment is marked as completed, update booking status to confirmed
        if ($request->payment_status === 'completed' && $payment->booking->booking_status === 'pending') {
            $payment->booking->booking_status = 'confirmed';
            $payment->booking->save();
        }

        // If payment is marked as failed or refunded, update booking status to cancelled
        if (in_array($request->payment_status, ['failed', 'refunded']) && $payment->booking->booking_status !== 'cancelled') {
            $payment->booking->booking_status = 'cancelled';
            $payment->booking->cancellation_reason = 'Payment ' . $request->payment_status;
            $payment->booking->save();
        }

        return response()->json([
            'result' => true,
            'message' => 'Payment status updated successfully',
            'data' => new PaymentResource($payment->load('booking.user:id,name,email'))
        ]);
    }

    /**
     * Get system settings (super admin only).
     */
    public function getSettings()
    {
        // You can implement this method to retrieve system settings
        // For example, cancellation policy, payment methods, etc.

        return response()->json([
            'result' => true,
            'message' => 'Settings retrieved successfully',
            'data' => [
                'cancellation_policy' => 'Bookings can be cancelled free of charge at least 24 hours before the check-in date.',
                'payment_methods' => ['credit_card', 'paypal', 'cash'],
                'tax_rate' => 10, // 10%
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
            ]
        ]);
    }

    /**
     * Update system settings (super admin only).
     */
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cancellation_policy' => 'nullable|string',
            'payment_methods' => 'nullable|array',
            'payment_methods.*' => 'string|in:credit_card,paypal,cash',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'check_in_time' => 'nullable|string|date_format:H:i',
            'check_out_time' => 'nullable|string|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        // You can implement this method to update system settings
        // For example, store settings in a settings table or config file

        return response()->json([
            'result' => true,
            'message' => 'Settings updated successfully',
            'data' => [
                'cancellation_policy' => $request->cancellation_policy ?? 'Bookings can be cancelled free of charge at least 24 hours before the check-in date.',
                'payment_methods' => $request->payment_methods ?? ['credit_card', 'paypal', 'cash'],
                'tax_rate' => $request->tax_rate ?? 10,
                'check_in_time' => $request->check_in_time ?? '14:00',
                'check_out_time' => $request->check_out_time ?? '12:00',
            ]
        ]);
    }

    // Get all admins
    public function getAdmins(Request $request)
    {
        $admins = User::where('role_id', 2)->get();

        return response()->json([
            'result' => true,
            'message' => 'Admins retrieved successfully',
            'data' => UserResource::collection($admins)
        ]);
    }

    // Get all bookings
    public function getAllBookingRooms(Request $request)
    {
        $bookings = Booking::with(['user:id,name,email', 'bookingRooms.room.roomType', 'payment'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'result' => true,
            'message' => 'Bookings retrieved successfully',
            'data' => BookingResource::collection($bookings)
        ]);
    }



    // Cancel a booking room by id (admin only, change status)
    public function cancelBookingRoom(Request $request, $id)
    {
        $user = $request->user();

        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'result' => false,
                'message' => 'Booking room not found',
                'data' => null
            ], 404);
        }

        // Only admin (role_id == 2) can cancel booking rooms
        if ($user->role_id !== 2) {
            return response()->json([
                'result' => false,
                'message' => 'You do not have permission to cancel booking rooms',
                'data' => null
            ], 403);
        }

        // Check if already cancelled or completed
        if ($booking->booking_status === 'cancelled') {
            return response()->json([
                'result' => false,
                'message' => 'Booking room is already cancelled',
                'data' => null
            ], 422);
        }
        if ($booking->booking_status === 'completed') {
            return response()->json([
                'result' => false,
                'message' => 'Cannot cancel a completed booking room',
                'data' => null
            ], 422);
        }

        // Check cancellation policy (24 hours before check-in)
        $checkInDate = new \DateTime($booking->check_in_date);
        $now = new \DateTime();
        $interval = $now->diff($checkInDate);
        $hoursDifference = ($interval->days * 24) + $interval->h + ($interval->i > 0 ? 1 : 0);

        if ($hoursDifference < 24 && $checkInDate > $now) {
            return response()->json([
                'result' => false,
                'message' => 'Cannot cancel booking room less than 24 hours before check-in',
                'data' => [
                    'cancellation_policy' => 'Booking rooms can only be cancelled at least 24 hours before check-in'
                ]
            ], 422);
        }

        // Cancel booking room
        $booking->booking_status = 'cancelled';
        $booking->cancellation_reason = $request->input('cancellation_reason');
        $booking->save();

        return response()->json([
            'result' => true,
            'message' => 'Booking room cancelled successfully',
            'data' => new BookingResource($booking)
        ]);
    }

    

    // Get all booking services (admin only)
    public function getAllBookingServices(Request $req)
    {
        $user = $req->user();
        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized',
                'data' => null
            ], 401);
        }
        // Only allow admins
        if ($user->role_id != 2) {
            return response()->json([
                'result' => false,
                'message' => 'Only admins can view all booking services',
                'data' => null
            ], 403);
        }

        $services = BookingService::with(['booking.user:id,name,email', 'service', 'serviceType'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'result' => true,
            'message' => 'All booking services retrieved successfully',
            'data' => $services
        ]);
    }


    /**
     * Delete a booking service by its ID.
     */
    public function deleteBookingServiceById(Request $req, $id)
    {
        $user = $req->user();
        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized',
                'data' => null
            ], 401);
        }

        // Find booking service by id
        $bookingService = BookingService::find($id);

        if (!$bookingService) {
            return response()->json([
                'result' => false,
                'message' => 'Booking service not found',
                'data' => null
            ], 404);
        }

        // Only allow regular users to delete their own booking service
        // Admins (role_id == 2) can delete any booking service
        if ($user->role_id == 1) {
            if ($bookingService->booking->user_id !== $user->id) {
                return response()->json([
                    'result' => false,
                    'message' => 'Unauthorized to delete this booking service',
                    'data' => null
                ], 403);
            }
        } elseif ($user->role_id != 2) {
            // Not admin or regular user
            return response()->json([
                'result' => false,
                'message' => 'You do not have permission to delete this booking service',
                'data' => null
            ], 403);
        }

        $bookingService->delete();

        return response()->json([
            'result' => true,
            'message' => 'Booking service deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Update a booking service by id (admin version)
     */
    public function updateBookingServiceById(Request $req, $id)
    {
        $user = $req->user();
        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized',
                'data' => null
            ], 401);
        }
        // Only allow regular users or admins
        if (!in_array($user->role_id, [1, 2])) {
            return response()->json([
                'result' => false,
                'message' => 'You do not have permission to update booking services',
                'data' => null
            ], 403);
        }

        // Validate request
        $req->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        // Find booking service by id
        $bookingService = BookingService::find($id);

        if (!$bookingService) {
            return response()->json([
                'result' => false,
                'message' => 'Booking service not found',
                'data' => null
            ], 404);
        }

        // Only allow regular users to update their own booking service
        // Admins (role_id == 2) can update any booking service
        if ($user->role_id == 1) {
            if ($bookingService->booking->user_id !== $user->id) {
                return response()->json([
                    'result' => false,
                    'message' => 'Unauthorized to update this booking service',
                    'data' => null
                ], 403);
            }
        }

        // Update booking service details
        $bookingService->quantity = $req->input('quantity');
        $bookingService->price = $req->input('price');
        $bookingService->save();

        return response()->json([
            'result' => true,
            'message' => 'Booking service updated successfully',
            'data' => $bookingService
        ]);
    }

    // Update booking by admin (admin can update any user's booking)
    public function updateBookingByAdmin(Request $req, $id)
    {
        $user = $req->user('sanctum');

        // Only admin (role_id == 2) can update any booking
        if (!$user || $user->role_id != 2) {
            return response()->json([
                'result' => false,
                'message' => 'Only admins can update bookings',
                'data' => null
            ], 403);
        }

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

    // Get regular user by id (admin only)
    public function getRegularUserById(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || $admin->role_id != 2) {
            return response()->json([
                'result' => false,
                'message' => 'Only admins can access regular user details',
                'data' => null
            ], 403);
        }

        $user = User::where('id', $id)->where('role_id', 1)->first();
        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }
        return response()->json([
            'result' => true,
            'message' => 'User retrieved successfully',
            'data' => new UserResource($user)
        ]);
    }

    // Admin confirm booking room by id
    public function confirmBookingRoom(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || $admin->role_id != 2) {
            return response()->json([
                'result' => false,
                'message' => 'Only admins can confirm booking rooms',
                'data' => null
            ], 403);
        }

        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json([
                'result' => false,
                'message' => 'Booking room not found',
                'data' => null
            ], 404);
        }

        // Only allow confirming pending bookings
        if ($booking->booking_status !== 'pending') {
            return response()->json([
                'result' => false,
                'message' => 'Booking room is not in pending status',
                'data' => null
            ], 422);
        }

        // Update booking status to confirmed
        $booking->booking_status = 'confirmed';
        $booking->save();

        return response()->json([
            'result' => true,
            'message' => 'Booking room confirmed successfully',
            'data' => new BookingResource($booking)
        ]);
    }
}
