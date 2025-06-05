<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Validator;
use App\Models\Booking;
use App\Models\BookingService;
use App\Http\Resources\BookingResource;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    //get all admins
    public function getAllAdmins(Request $request)
    {
        $admins = User::where('role_id', 2)->get();

        return response()->json([
            'result' => true,
            'message' => 'Admins retrieved successfully',
            'data' => UserResource::collection($admins)
        ]);
    }
    
    //get all super admins
    public function getSuperAdmins(Request $request)
    {
        $superAdmins = User::whereHas('role', function($query) {
            $query->where('name', 'super admin');
        })->get();

        return response()->json([
            'result' => true,
            'message' => 'Super admins retrieved successfully',
            'data' => UserResource::collection($superAdmins)
        ]);
    }
    //get all users (regular users with role_id = 1)
    public function getUsers(Request $request)
    {
        $users = User::where('role_id', 1)->get();

        return response()->json([
            'result' => true,
            'message' => 'Regular ssers retrieved successfully',
            'data' => UserResource::collection($users)
        ]);
    }
    
    // update user by id
    public function updateUser(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:250',
            'email' => 'required|email|max:250',
            'gender' => 'nullable|in:male,female,other',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Handle avatar upload if present
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->storePublicly('public/superAdmins', 's3');
            $data['avatar'] = $avatarPath;
        }

        $user->fill($data);
        $user->save();

        return response()->json([
            'result' => true,
            'message' => 'User updated successfully',
            'data' => new UserResource($user),
        ]);
    }

    //delete user by id
    public function deleteUser(Request $request, User $user)
    {
        // Only allow deleting regular users (role_id = 1)
        if ($user->role_id != 1) {
            return response()->json([
                'result' => false,
                'message' => 'Only regular users can be deleted.',
            ], 403);
        }

        try {
            $user->delete();

            return response()->json([
                'result' => true,
                'message' => 'User deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // update admin by id
    public function updateAdmin(Request $request, $id)
    {
        $admin = User::find($id);
        if (!$admin || $admin->role_id != 2) {
            return response()->json([
                'result' => false,
                'message' => 'Admin not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:250',
            'email' => 'required|email|max:250',
            'gender' => 'nullable|in:male,female,other',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Handle avatar upload if present
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->storePublicly('public/superAdmins', 's3');
            $data['avatar'] = $avatarPath;
        }

        $admin->fill($data);
        $admin->save();

        return response()->json([
            'result' => true,
            'message' => 'Admin updated successfully',
            'data' => new UserResource($admin),
        ]);
    }

    //delete admin by id
    public function deleteAdmin(Request $request, $id)
    {
        $admin = User::find($id);
        if (!$admin || $admin->role_id != 2) {
            return response()->json([
                'result' => false,
                'message' => 'Admin not found',
            ], 404);
        }

        try {
            $admin->delete();

            return response()->json([
                'result' => true,
                'message' => 'Admin deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => 'Failed to delete admin',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Get all booking rooms (super admin only)
    public function getAllBookingRooms(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized',
                'data' => null
            ], 401);
        }
        if ($user->role_id != 3) {
            return response()->json([
                'result' => false,
                'message' => 'Only super admins can view all bookings',
                'data' => null
            ], 403);
        }

        $bookings = Booking::with(['user:id,name,email', 'bookingRooms.room.roomType', 'payment'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'result' => true,
            'message' => 'Bookings retrieved successfully',
            'data' => BookingResource::collection($bookings)
        ]);
    }

    // Cancel a booking room by id (super admin only)
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

        // Only super admin (role_id == 3) can cancel booking rooms
        if ($user->role_id !== 3) {
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

    // Get all booking services (super admin only)
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
        // Only allow super admins
        if ($user->role_id != 3) {
            return response()->json([
                'result' => false,
                'message' => 'Only super admins can view all booking services',
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
     * Delete a booking service by its ID (super admin or owner).
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
        // Super Admins (role_id == 3) can delete any booking service
        if ($user->role_id == 1) {
            if ($bookingService->booking->user_id !== $user->id) {
                return response()->json([
                    'result' => false,
                    'message' => 'Unauthorized to delete this booking service',
                    'data' => null
                ], 403);
            }
        } elseif ($user->role_id != 3) {
            // Not super admin or regular user
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
     * Update a booking service by id
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
        // Only allow regular users or super admins
        if (!in_array($user->role_id, [1, 3])) {
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
        // Super admins (role_id == 3) can update any booking service
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

    /**
     * Update booking by super admin (super admin can update any user's booking)
     */
    public function updateBookingBySuperAdmin(Request $req, $id)
    {
        $user = $req->user();

        // Only super admin (role_id == 3) can update any booking
        if (!$user || $user->role_id != 3) {
            return response()->json([
                'result' => false,
                'message' => 'Only super admins can update bookings',
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
            $unavailableRooms = \App\Models\Room::whereIn('id', $roomIds)
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
                \App\Models\BookingRoom::where('booking_id', $booking->id)->delete();
                // Add new rooms
                foreach ($roomIds as $roomId) {
                    $bookingRoom = new \App\Models\BookingRoom();
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

    // Get regular user by id (super admin only)
    public function getRegularUserById(Request $request, $id)
    {
        $superAdmin = $request->user();
        if (!$superAdmin || $superAdmin->role_id != 3) {
            return response()->json([
                'result' => false,
                'message' => 'Only super admins can access regular user details',
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

    // Super admin confirm booking room by id
    public function confirmBookingRoomBySuperAdmin(Request $request, $id)
    {
        $superAdmin = $request->user();
        if (!$superAdmin || $superAdmin->role_id != 3) {
            return response()->json([
                'result' => false,
                'message' => 'Only super admins can confirm booking rooms',
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
