<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Validator;

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
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
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
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
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

}
