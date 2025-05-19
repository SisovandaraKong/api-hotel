<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsLogin
{

    public function handle(Request $req, Closure $next): Response
    {
        // check if user is login
        $loginUser =  $req->user('sanctum');
        if (!$loginUser) {
            return response()->json([
                'result' => false,
                'message' => 'You need to login first.',
            ], 401);
        }

        // go to next gate
        return $next($req);
    }
}
