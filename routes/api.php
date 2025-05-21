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
// Get all room types
Route::get('/room-types', [RoomTypeController::class, 'index']);
// Protected routes - require login
Route::middleware(['auth:sanctum', IsLogin::class])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);


    // User profile routes
    Route::get('/profile', [UserController::class, 'profile']);
    Route::post('/profile', [UserController::class, 'updateProfile']);
    Route::put('/change-password', [UserController::class, 'changePassword']);

    // Booking routes
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::put('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

    // Rating routes
    Route::post('/ratings', [RatingController::class, 'store']);
    Route::put('/ratings/{id}', [RatingController::class, 'update']);
    Route::delete('/ratings/{id}', [RatingController::class, 'destroy']);


    // Admin routes
    Route::middleware(['auth:sanctum',IsAdmin::class])->group(function () {
        // Room management
        Route::post('/rooms', [RoomController::class, 'store']);
        Route::put('/rooms/{id}', [RoomController::class, 'update']);
        Route::delete('/rooms/{id}', [RoomController::class, 'destroy']);

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
        // Admin management
        Route::post('/admin/users', [AdminController::class, 'createUser']);
        Route::put('/admin/users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);
    });
});
