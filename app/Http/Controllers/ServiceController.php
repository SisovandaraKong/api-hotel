<?php

namespace App\Http\Controllers;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $services = Service::all();
        return response()->json([
            'status' => 'success',
            'message' => 'Services retrieved successfully.',
            'data' => $services
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'available' => 'boolean',
            'service_type_id' => 'required|exists:service_types,id',
        ]);

        $service = Service::create($validatedData);
        return response()->json([
            'status' => 'success',
            'message' => 'Service created successfully.',
            'data' => $service
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $service = Service::find($id);
        if (!$service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service not found',
                'data' => null
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Service retrieved successfully.',
            'data' => $service
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $service = Service::find($id);
        if (!$service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service not found',
                'data' => null
            ], 404);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'available' => 'boolean',
            'service_type_id' => 'required|exists:service_types,id',
        ]);

        $service->update($validatedData);
        return response()->json([
            'status' => 'success',
            'message' => 'Service updated successfully.',
            'data' => $service
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $service = Service::find($id);
        if (!$service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service not found',
                'data' => null
            ], 404);
        }

        $service->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Service deleted successfully.',
            'data' => null
        ]);
    }
}
