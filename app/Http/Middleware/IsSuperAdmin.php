<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsSuperAdmin
{
    public function handle(Request $req, Closure $next): Response
    {
        $loginUser = $req->user('sanctum');
        if ($loginUser->role_id != 3) {
            return response()->json([
                'result' => false,
                'message' => 'You don\'t have permission to access.'
            ], 401);
        }
        
        return $next($req);
    }
}
