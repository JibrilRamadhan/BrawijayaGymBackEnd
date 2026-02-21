<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'is_guest' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully.',
            'data' => [
                'uuid' => $user->uuid,
                'username' => $user->username,
                'email' => $user->email,
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Login user and create token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string', // can be email or username
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->login)
            ->orWhere('username', $request->login)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'uuid' => $user->uuid,
                'username' => $user->username,
                'email' => $user->email,
            ]
        ]);
    }

    /**
     * Get authenticated user profile with active subscription.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        // Always refresh is_guest from DB to get the latest value
        $user->refresh();

        // Get active subscription with plan details
        $activeSubscription = $user->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->orderBy('end_date', 'desc')
            ->first();

        $data = [
            'uuid' => $user->uuid,
            'username' => $user->username,
            'email' => $user->email,
            'is_guest' => $user->is_guest,
        ];

        if ($activeSubscription) {
            $data['subscription'] = [
                'uuid' => $activeSubscription->uuid,
                'plan_name' => $activeSubscription->plan->name,
                'plan_type' => $activeSubscription->plan->type,
                'start_date' => $activeSubscription->start_date,
                'end_date' => $activeSubscription->end_date,
                'status' => $activeSubscription->status,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }
}
