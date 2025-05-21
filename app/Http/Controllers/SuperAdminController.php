<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\UserResource;

class SuperAdminController extends Controller
{
    //get all admins
    public function getAllAdmins(Request $request)
    {
        $admins = User::whereHas('role', function($query) {
            $query->where('name', 'admin');
        })->get();

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
    //get all users
    public function getUsers(Request $request)
    {
        $users = User::all();

        return response()->json([
            'result' => true,
            'message' => 'Users retrieved successfully',
            'data' => UserResource::collection($users)
        ]);
    }
    //delete user by id
    public function deleteUser(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user->delete();

        return response()->json([
            'result' => true,
            'message' => 'User deleted successfully',
        ]);
    }
    //delete admin by id
    public function deleteAdmin(Request $request, $id)
    {
        $admin = User::find($id);
        if (!$admin) {
            return response()->json([
                'result' => false,
                'message' => 'Admin not found',
            ], 404);
        }

        $admin->delete();

        return response()->json([
            'result' => true,
            'message' => 'Admin deleted successfully',
        ]);
    }
    //delete super admin by id
    public function deleteSuperAdmin(Request $request, $id)
    {
        $superAdmin = User::find($id);
        if (!$superAdmin) {
            return response()->json([
                'result' => false,
                'message' => 'Super admin not found',
            ], 404);
        }

        $superAdmin->delete();

        return response()->json([
            'result' => true,
            'message' => 'Super admin deleted successfully',
        ]);
    }
}
