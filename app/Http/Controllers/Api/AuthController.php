<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\GeneratedPasswordMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * - Trial & Harian: no password, no username (is_guest = true)
     * - Member: auto-generate password, send via email (is_guest = false)
     */
    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'plan_type' => 'required|in:trial,harian,member',
        ]);

        $isMember = $request->plan_type === 'member';
        $password = null;

        if ($isMember) {
            $password = Str::random(10);
        }

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'email' => $request->email,
            'name' => $request->name,
            'phone' => $request->phone,
            'username' => null,
            'password' => $password ? Hash::make($password) : null,
            'is_guest' => !$isMember,
        ]);

        // Send generated password via email for member plans
        if ($isMember && $password) {
            try {
                Mail::to($user->email)->send(new GeneratedPasswordMail($user->name, $password));
            } catch (\Exception $e) {
                // Log error but don't fail registration
                \Log::error('Failed to send password email: ' . $e->getMessage());
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $responseData = [
            'message' => 'User registered successfully.',
            'data' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_guest' => $user->is_guest,
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ];

        if ($isMember) {
            $responseData['data']['password_sent'] = true;
            $responseData['message'] = 'User registered successfully. Password has been sent to your email.';
        }

        return response()->json($responseData, 201);
    }

    /**
     * Login user and create token.
     * Only users with a password (member) can login.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('roles:id,name')->where('email', $request->email)->first();

        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Akun Anda telah dinonaktifkan oleh Administrator.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'is_guest' => $user->is_guest,
                'is_active' => $user->is_active,
                'roles' => $user->roles
            ]
        ]);
    }

    /**
     * Get authenticated user profile with active subscription.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        // Always refresh is_guest and load roles from DB to get the latest value
        $user->refresh();
        $user->load('roles:id,name');

        // Get active subscription with plan details
        $activeSubscription = $user->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->orderBy('end_date', 'desc')
            ->first();

        $data = [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_guest' => $user->is_guest,
            'is_active' => $user->is_active,
            'roles' => $user->roles,
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
