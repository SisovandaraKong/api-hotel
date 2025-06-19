<?php
// namespace App\Http\Controllers;

// use App\Models\User;
// use Illuminate\Http\Request;
// use Laravel\Socialite\Facades\Socialite;
// use Illuminate\Support\Str;

// class SocialAuthController extends Controller
// {
//     public function redirectToGoogle() {
//         return Socialite::driver('google')->stateless()->redirect();
//     }

//     public function handleGoogleCallback() {
//         $googleUser = Socialite::driver('google')->stateless()->user();
//         return $this->handleSocialLogin($googleUser);
//     }

//     public function redirectToGithub() {
//         return Socialite::driver('github')->stateless()->redirect();
//     }

//     public function handleGithubCallback() {
//         $githubUser = Socialite::driver('github')->stateless()->user();
//         return $this->handleSocialLogin($githubUser);
//     }

//     private function handleSocialLogin($socialUser) {
//         $user = User::where('email', $socialUser->getEmail())->first();

//         if (!$user) {
//             $user = User::create([
//                 'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Unknown',
//                 'email' => $socialUser->getEmail(),
//                 'password' => bcrypt(Str::random(16)),
//                 'avatar' => $socialUser->getAvatar(),
//                 'role_id' => 1, // normal user
//             ]);
//         }

//         // Create Sanctum token
//         $token = $user->createToken($user->id)->plainTextToken;

//         return response()->json([
//             'result' => true,
//             'message' => 'Logged in via Social Login',
//             'data' => [
//                 'user' => $user,
//                 'token' => $token,
//             ]
//         ]);
//     }
// }
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function redirectToGoogle() {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        return $this->handleSocialLogin($googleUser); // return the redirect
    }

    public function redirectToGithub() {
        return Socialite::driver('github')->stateless()->redirect();
    }

    public function handleGithubCallback() {
        $githubUser = Socialite::driver('github')->stateless()->user();
        return $this->handleSocialLogin($githubUser); // return the redirect
    }

    private function handleSocialLogin($socialUser) {
        $user = User::where('email', $socialUser->getEmail())->first();

        if (!$user) {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Unknown',
                'email' => $socialUser->getEmail(),
                'password' => bcrypt(Str::random(16)),
                'avatar' => $socialUser->getAvatar(),
                'role_id' => 1,
            ]);
        }

        // Redirect to frontend and pass email (optional)
        $frontendUrl = 'https://rumsay-hotel.vercel.app/social/callback?email=' . urlencode($user->email);
        return redirect()->away($frontendUrl);
    }

    public function getUserByEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'User not found',
            ], 404);
        }

        $token = $user->createToken($user->id)->plainTextToken;

        return response()->json([
            'result' => true,
            'message' => 'User found',
            'data' => [
                'user' => $user,
                'token' => $token,
            ]
        ]);
    }
}

