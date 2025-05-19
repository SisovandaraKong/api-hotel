<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\UserResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
}
