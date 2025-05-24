<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsLogin;
use App\Http\Middleware\IsSuperAdmin;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\BookingServiceController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/register-admin', [AuthController::class, 'registerAdmin']);
Route::post('/register-super-admin', [AuthController::class, 'registerSuperAdmin']);
// Auth routes

Route::post('/auth/login', [AuthController::class, 'login']);


// Public room routes
Route::prefix('rooms')->group(function () {
    Route::get('/', [RoomController::class, 'index']);
    Route::get('/{id}', [RoomController::class, 'show']);
    Route::get('/{roomId}/ratings', [RatingController::class, 'getRoomRatings']);
});

// Cancellation policy (public)
Route::get('/cancellation-policy', [BookingController::class, 'getCancellationPolicy']);

//Get all users
Route::get('/users', [UserController::class, 'index']);

// Get all admins
Route::get('/admins', [SuperAdminController::class, 'getAllAdmins']);

// Get all super admins
Route::get('/super-admins', [SuperAdminController::class, 'getSuperAdmins']);

// Get all room types
Route::get('/room-types', [RoomTypeController::class, 'index']);

// Get all rooms
Route::get('/rooms', [RoomController::class, 'rooms']);

// Get all regular users
Route::get('/regularUsers', [UserController::class, 'regularUsers']);


// Protected routes - require login
Route::middleware(['auth:sanctum', IsLogin::class])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);


    // User profile routes
    Route::get('/profile', [UserController::class, 'profile']);
    Route::post('/profile', [UserController::class, 'updateProfile']);
    Route::put('/change-password', [UserController::class, 'changePassword']);

    // Booking Rooms routes
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::delete('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
    Route::put('/bookings/{id}/update', [BookingController::class, 'update']);
    Route::get('/cancellation-policy', [BookingController::class, 'getCancellationPolicy']);

    // Booking services routes
    Route::get('/bookingServices', [BookingController::class, 'getAllBookingServices']);
    Route::post('/bookingServices', [BookingController::class, 'createBookingWithService']);
    Route::get('/bookingServices/{id}', [BookingController::class, 'getBookingServiceById']);
    Route::put('/bookingServices/{id}', [BookingController::class, 'updateBookingServiceById']);
    Route::delete('/bookingServices/{id}', [BookingController::class, 'deleteBookingServiceById']);

    // Rating routes
    Route::post('/ratings', [RatingController::class, 'store']);
    Route::put('/ratings/{id}', [RatingController::class, 'update']);
    Route::delete('/ratings/{id}', [RatingController::class, 'destroy']);


    // Admin routes
    Route::middleware(['auth:sanctum',IsAdmin::class])->group(function () {
        // Room management
        Route::get('/admin/rooms', [RoomController::class, 'index']);
        Route::post('/admin/rooms', [RoomController::class, 'store']);
        Route::put('/admin/rooms/{id}', [RoomController::class, 'update']);
        Route::delete('/admin/rooms/{id}', [RoomController::class, 'destroy']);

        // Room type management
        Route::post('/room-types', [RoomTypeController::class, 'store']);
        Route::put('/room-types/{id}', [RoomTypeController::class, 'update']);
        Route::delete('/room-types/{id}', [RoomTypeController::class, 'destroy']);

        // Booking management (admin)
        Route::get('/admin/bookings', [AdminController::class, 'getAllBookings']);
        Route::put('/admin/bookings/{id}/status', [AdminController::class, 'updateBookingStatus']);

        // User management (admin)
        Route::get('/admin/users', [AdminController::class, 'getAllUsers']);
    });

    // Super admin routes
    Route::middleware(['auth:sanctum',IsSuperAdmin::class])->group(function () {

    // Service type management
    Route::get('/service-types', [ServiceTypeController::class, 'index']);
    Route::post('/service-types', [ServiceTypeController::class, 'store']);
    Route::get('/service-types/{id}', [ServiceTypeController::class, 'show']);
    Route::put('/service-types/{id}', [ServiceTypeController::class, 'update']);
    Route::delete('/service-types/{id}', [ServiceTypeController::class, 'destroy']);
    
    // Service management
    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);
    Route::get('/services/{id}', [ServiceController::class, 'show']);
    Route::put('/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);

    // User management
    Route::get('/superAdmin/regular-user', [SuperAdminController::class, 'getUsers']);
    Route::put('/superAdmin/regular-user/{user}', [SuperAdminController::class, 'updateUser']);
    Route::delete('/superAdmin/regular-user/{user}', [SuperAdminController::class, 'deleteUser']);
    
    // Admin management
    Route::get('/superAdmin/admins', [SuperAdminController::class, 'getAllAdmins']);
    Route::put('/superAdmin/admin/{id}', [SuperAdminController::class, 'updateAdmin']);
    Route::delete('/superAdmin/admin/{id}', [SuperAdminController::class, 'deleteAdmin']);
    });
});
