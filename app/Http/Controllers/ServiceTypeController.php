<?php

// app/Http/Controllers/ServiceTypeController.php
namespace App\Http\Controllers;

use App\Http\Resources\ServiceTypeResource;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceTypeController extends Controller
{
    public function index()
    {
        return response()->json([
            'result' => true,
            'message' => 'Service types retrieved successfully',
            'data' => ServiceTypeResource::collection(ServiceType::all())
        ], 200); // 200 OK
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:service_types,name',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422); // 422 Unprocessable Entity
        }

        $serviceType = ServiceType::create($request->only('name', 'description'));

        return response()->json([
            'result' => true,
            'message' => 'Service type created successfully',
            'data' => new ServiceTypeResource($serviceType)
        ], 201); // 201 Created
    }

    public function show($id)
    {
        $type = ServiceType::find($id);

        if (!$type) {
            return response()->json([
                'result' => false,
                'message' => 'Service type not found'
            ], 404); // 404 Not Found
        }

        return response()->json([
            'result' => true,
            'message' => 'Service type retrieved successfully',
            'data' => new ServiceTypeResource($type)
        ], 200); // 200 OK
    }

    public function update(Request $request, $id)
    {
        $type = ServiceType::find($id);

        if (!$type) {
            return response()->json([
                'result' => false,
                'message' => 'Service type not found'
            ], 404); // 404 Not Found
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:service_types,name,' . $id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422); // 422 Unprocessable Entity
        }

        $type->update($request->only('name', 'description'));

        return response()->json([
            'result' => true,
            'message' => 'Service type updated successfully',
            'data' => new ServiceTypeResource($type)
        ], 200); // 200 OK
    }

    public function destroy($id)
    {
        $type = ServiceType::find($id);

        if (!$type) {
            return response()->json([
                'result' => false,
                'message' => 'Service type not found'
            ], 404); // 404 Not Found
        }

        $type->delete();

        return response()->json([
            'result' => true,
            'message' => 'Service type deleted successfully'
        ], 200); // 200 OK
    }
}

