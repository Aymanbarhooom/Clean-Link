<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['logout', 'me']);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fullname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'fullname' => $validated['fullname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'client', // Registration is strictly restricted to clients
        ]);

        $user->profile()->create(); // Initialize blank profile record

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'access_token' => $token,
        ], 'Client registered successfully', 211);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::make($validated['password'], ['fallback' => $user->password])) {
            return $this->errorResponse('Invalid operational login credentials', 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user->load('profile'),
            'access_token' => $token,
        ], 'Logged in successfully');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse([], 'Logged out successfully from session token context');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'workerProfile', 'fcmTokens']);
        return $this->successResponse($user, 'Current user profile metrics retrieved');
    }
    /**
     * Refresh or store the device Firebase Cloud Messaging (FCM) Token context.
     * Route endpoint: POST /api/auth/fcm-token
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
            'device_type' => 'nullable|string|in:android,ios,web',
        ]);

        // upsert token context bound cleanly to authenticating sessions
        $tokenRecord = FcmToken::updateOrCreate(
            ['token' => $validated['fcm_token']],
            [
                'user_id' => auth()->id(),
                'device_type' => $validated['device_type'] ?? null
            ]
        );

        return $this->successResponse($tokenRecord, 'FCM device token synced successfully');
    }

}
