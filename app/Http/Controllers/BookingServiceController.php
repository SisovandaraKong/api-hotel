<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BookingService;

class BookingServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bookingServices = BookingService::all();

        return response()->json([
            'success' => true,
            'message' => 'Booking services retrieved successfully.',
            'data' => $bookingServices
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'service_id' => 'required|exists:services,id',
            'service_type_id' => 'required|exists:service_types,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        $bookingService = BookingService::create($validatedData);
        return response()->json([
            'success' => true,
            'message' => 'Booking service created successfully.',
            'data' => $bookingService
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $bookingService = BookingService::find($id);
        if (!$bookingService) {
            return response()->json([
                'success' => false,
                'message' => 'Booking service not found.',
                'data' => null
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Booking service retrieved successfully.',
            'data' => $bookingService
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $bookingService = BookingService::find($id);
        if (!$bookingService) {
            return response()->json([
                'success' => false,
                'message' => 'Booking service not found.',
                'data' => null
            ], 404);
        }

        $validatedData = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'service_id' => 'required|exists:services,id',
            'service_type_id' => 'required|exists:service_types,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        $bookingService->update($validatedData);
        return response()->json([
            'success' => true,
            'message' => 'Booking service updated successfully.',
            'data' => $bookingService
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $bookingService = BookingService::find($id);
        if (!$bookingService) {
            return response()->json([
                'success' => false,
                'message' => 'Booking service not found.',
                'data' => null
            ], 404);
        }

        $bookingService->delete();
        return response()->json([
            'success' => true,
            'message' => 'Booking service deleted successfully.',
            'data' => null
        ], 200);
    }
}
