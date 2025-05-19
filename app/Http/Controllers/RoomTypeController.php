<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomTypeResource;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomTypeController extends Controller
{
    /**
     * Display a listing of the room types.
     */
    public function index()
    {
        $roomTypes = RoomType::all();

        return response()->json([
            'result' => true,
            'message' => 'Room types retrieved successfully',
            'data' => RoomTypeResource::collection($roomTypes)
        ]);
    }

    /**
     * Store a newly created room type in storage.
     * Only accessible by admin and superadmin.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:50|unique:room_types,type',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $roomType = RoomType::create([
            'type' => $request->type,
            'price' => $request->price,
            'description' => $request->description,
            'capacity' => $request->capacity,
        ]);

        return response()->json([
            'result' => true,
            'message' => 'Room type created successfully',
            'data' => new RoomTypeResource($roomType)
        ]);
    }

    /**
     * Display the specified room type.
     */
    public function show($id)
    {
        $roomType = RoomType::find($id);

        if (!$roomType) {
            return response()->json([
                'result' => false,
                'message' => 'Room type not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'result' => true,
            'message' => 'Room type retrieved successfully',
            'data' => new RoomTypeResource($roomType)
        ]);
    }

    /**
     * Update the specified room type in storage.
     * Only accessible by admin and superadmin.
     */
    public function update(Request $request, $id)
    {
        $roomType = RoomType::find($id);

        if (!$roomType) {
            return response()->json([
                'result' => false,
                'message' => 'Room type not found',
                'data' => null
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:50|unique:room_types,type,' . $id,
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $roomType->update([
            'type' => $request->type,
            'price' => $request->price,
            'description' => $request->description,
            'capacity' => $request->capacity,
        ]);

        return response()->json([
            'result' => true,
            'message' => 'Room type updated successfully',
            'data' => new RoomTypeResource($roomType)
        ]);
    }

    /**
     * Remove the specified room type from storage.
     * Only accessible by admin and superadmin.
     */
    public function destroy($id)
    {
        $roomType = RoomType::find($id);

        if (!$roomType) {
            return response()->json([
                'result' => false,
                'message' => 'Room type not found',
                'data' => null
            ], 404);
        }

        // Check if there are rooms using this room type
        if ($roomType->rooms()->count() > 0) {
            return response()->json([
                'result' => false,
                'message' => 'Cannot delete room type that is in use',
                'data' => null
            ], 422);
        }

        $roomType->delete();

        return response()->json([
            'result' => true,
            'message' => 'Room type deleted successfully',
            'data' => null
        ]);
    }
}
