<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;



class AuthController extends Controller
{
    public function register(Request $req) {
        // validation
        $req->validate([
            'name' => ['required','string','max:250'],
            'avatar' => ['nullable','file','mimetypes:image/png,image/jpeg','max:2048'],
            'email' => ['required','email','max:250'],
            'password' => ['required','string','min:8','max:250','confirmed'],
            'gender' => ['nullable','string','in:male,female,other'],
        ]);

        // store avatar
        $avatar = 'public/users/no_photo.jpg'; // set default avatar
        if ($req->hasFile('avatar')) {
            $avatar = $req->file('avatar')->storePublicly('public/users', ['disk' => 's3']);
        }

        $req->merge(['role_id' => 1]); // <-- set default role_id for normal user

        $user = new User($req->only(['name', 'role_id', 'email', 'password', 'gender']));
        $user->avatar = $avatar; // store path only
        $user->save();

        // generate token
        $token = $user->createToken($user->id)->plainTextToken;

        // response back
        return response()->json([
            'result' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => $user,
                'avatar_url' => 'https://romsaydev.s3.us-east-1.amazonaws.com/' . $user->avatar,
                'token' => $token,
            ]
        ]);
    }

    public function login(Request $req) {
        // validation
        $req->validate([
            'email' => ['required','email','max:250'],
            'password' => ['required','string','min:8','max:250']
        ]);

        // check email
        $user = new User();
        $user = $user->where('email', $req->input('email'))->first(['id', 'password']);
        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'Incorrect email or password! Check again.'
            ], 401); // <-- set status code 401
        }

        // check password
        if (!Hash::check($req->input('password'), $user->password)) {
            return response()->json([
                'result' => false,
                'message' => 'Incorrect email or password! Check again.'
            ], 401); // <-- set status code 401
        }

        // generate token
        $token = $user->createToken($user->id)->plainTextToken;

        // response back
        return response()->json([
            'result' => true,
            'message' => 'Logged in successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
            ]
        ]);
    }

    //logout
    public function logout(Request $req) {
        $user = $req->user('sanctum');
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'result' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request)
    {
        return response()->json([
            'result' => true,
            'message' => 'User retrieved successfully',
            'data' => new UserResource($request->user())
        ]);
    }
        public function registerAdmin(Request $req) {
            // validation
            $req->validate([
                'name' => ['required','string','max:250'],
                'avatar' => ['nullable','file','mimetypes:image/png,image/jpeg','max:2048'],
                'email' => ['required','email','max:250'],
                'password' => ['required','string','min:8','max:250','confirmed'],
                'gender' => ['nullable','string','in:male,female,other'],
            ]);

            $avatar = 'admin/no_photo.jpg'; // default for admin
            if ($req->hasFile('avatar')) {
                $avatar = $req->file('avatar')->storePublicly('admin', ['disk' => 's3']);
            }

            $req->merge(['role_id' => 2]); // set admin role

            $user = new User($req->only(['name', 'role_id', 'email', 'password', 'gender']));
            $user->avatar = $avatar;
            $user->save();

            $token = $user->createToken($user->id)->plainTextToken;

            return response()->json([
                'result' => true,
                'message' => 'Admin registered successfully',
                'data' => [
                    'user' => $user,
                    'avatar_url' => 'https://romsaydev.s3.us-east-1.amazonaws.com/' . $user->avatar,
                    'token' => $token,
                ]
            ]);
        }

        public function registerSuperAdmin(Request $req) {
            // validation
            $req->validate([
                'name' => ['required','string','max:250'],
                'avatar' => ['nullable','file','mimetypes:image/png,image/jpeg','max:2048'],
                'email' => ['required','email','max:250'],
                'password' => ['required','string','min:8','max:250','confirmed'],
                'gender' => ['nullable','string','in:male,female,other'],
            ]);

            $avatar = 'superAdmin/no_photo.jpg'; // default for super admin
            if ($req->hasFile('avatar')) {
                $avatar = $req->file('avatar')->storePublicly('superAdmin', ['disk' => 's3']);
            }

            $req->merge(['role_id' => 3]); // set super admin role

            $user = new User($req->only(['name', 'role_id', 'email', 'password', 'gender']));
            $user->avatar = $avatar;
            $user->save();

            $token = $user->createToken($user->id)->plainTextToken;

            return response()->json([
                'result' => true,
                'message' => 'Super Admin registered successfully',
                'data' => [
                    'user' => $user,
                    'avatar_url' => 'https://romsaydev.s3.us-east-1.amazonaws.com/' . $user->avatar,
                    'token' => $token,
                ]
            ]);
        }

    }
