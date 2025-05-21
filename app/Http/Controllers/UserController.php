<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\User;



class UserController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'result' => true,
            'message' => 'User profile retrieved successfully',
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'gender' => 'nullable|string|in:male,female,other',
            'avatar' => 'nullable|file|mimetypes:image/jpeg,image/png,image/jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if it's not the default
            if ($user->avatar !== 'user/no_photo.jpg') {
                Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar in 'user' directory
            $avatarPath = $request->file('avatar')->store('user', ['disk' => 'public']);
            $user->avatar = $avatarPath;
        }

        // Update user data
        $user->name = $request->name;
        $user->email = $request->email;
        $user->gender = $request->gender;
        $user->save();

        return response()->json([
            'result' => true,
            'message' => 'Profile updated successfully',
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        // Check if current password is correct
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'result' => false,
                'message' => 'Current password is incorrect',
                'data' => null
            ], 422);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'result' => true,
            'message' => 'Password changed successfully',
            'data' => null
        ]);
    }
    /**
     * Get all users.
     */
    public function index()
    {
        $users = User::all();
        return response()->json([
            'result' => true,
            'message' => 'Users retrieved successfully',
            'data' => UserResource::collection($users)
        ]);
    }
}
