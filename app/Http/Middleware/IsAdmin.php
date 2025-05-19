<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $req, Closure $next): Response
    {
        $loginUser = $req->user('sanctum');
        if ($loginUser->role_id != 2) {
            return response()->json([
                'result' => false,
                'message' => 'You do not have permission to access.'
            ], 401);
        }

        return $next($req);
    }
}
