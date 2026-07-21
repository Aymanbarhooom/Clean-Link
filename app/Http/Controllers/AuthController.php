<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\EmailVerification;
use App\Models\FcmToken;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

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

        // توليد كود OTP بسيط (مثلاً 6 أرقام)
        $otpCode = rand(100000, 999999);

        // حفظ الـ OTP في الجدول
        EmailVerification::create([
            'email' => $validated['email'],
            'otp_code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // إرسال الإيميل
        Mail::to($validated['email'])->send(new OtpMail($otpCode, $validated['fullname']));

        return $this->successResponse([
            'email' => $validated['email'],
        ], 'OTP sent to email. Please verify to complete registration.', 210);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fullname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
            'otp_code' => 'required|digits:6',
        ]);

        // البحث عن سجل الـ OTP
        $verification = EmailVerification::where('email', $validated['email'])
            ->where('otp_code', $validated['otp_code'])
            ->where('is_used', false)
            ->first();

        if (! $verification) {
            return $this->errorResponse('Invalid OTP code', 400);
        }

        // التحقق من انتهاء الصلاحية
        if ($verification->expires_at->isPast()) {
            return $this->errorResponse('OTP code has expired', 400);
        }

        // تعليم الـ OTP أنه تم استخدامه
        $verification->update(['is_used' => true]);

        // إنشاء المستخدم فعلياً
        $user = User::create([
            'fullname' => $validated['fullname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'client',
        ]);

        $user->profile()->create();

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'access_token' => $token,
        ], 'Client registered and verified successfully', 211);
    }

    public function resendOtp(Request $request): JsonResponse
{
    $validated = $request->validate([
        'fullname' => 'required|string|max:255',
        'email' => 'required|string|email|max:255',
    ]);

    // تأكد أن الإيميل غير موجود في جدول users (يعني لم يُسجّل بعد)
    if (\App\Models\User::where('email', $validated['email'])->exists()) {
        return $this->errorResponse('This email is already registered', 400);
    }

    // احذف أي OTP قديم غير مستخدم
    EmailVerification::where('email', $validated['email'])
        ->where('is_used', false)
        ->delete();

    // توليد كود جديد
    $otpCode = rand(100000, 999999);

    // إنشاء سجل جديد
    EmailVerification::create([
        'email' => $validated['email'],
        'otp_code' => $otpCode,
        'expires_at' => Carbon::now()->addMinutes(10),
    ]);

    // إرسال الإيميل
    Mail::to($validated['email'])->send(new OtpMail($otpCode, $validated['fullname']));

    return $this->successResponse([
        'email' => $validated['email'],
    ], 'A new OTP has been sent to your email', 210);
}

    public function login(Request $request): JsonResponse
    {
        // 1. التحقق من البيانات المدخلة
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
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
            'fcm_token'   => 'required|string',
            'device_type' => 'nullable|string|in:android,ios,web',
            'lang'        => 'nullable|string|size:2',
        ]);

        $deviceLang = $validated['lang'] ?? app()->getLocale();

        $tokenRecord = FcmToken::updateOrCreate(
            ['token' => $validated['fcm_token']],
            [
                'user_id'     => auth()->id(),
                'device_type' => $validated['device_type'] ?? null,
                'lang'        => $deviceLang
            ]
        );

        return $this->successResponse($tokenRecord, 'FCM device token synced successfully');
    }

    /**
     * Update Account Profile Information (User + Profile Models)
     * Route: PUT /api/auth/profile/update
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'fullname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('profile_images', 'public');
            $validated['image'] = $path;
        }

        // Update core user records
        $user->update([
            'fullname' => $validated['fullname'],
            'email' => $validated['email'],
        ]);

        // Update linked profile parameters
        $user->profile()->update([
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'image' => $validated['image'] ?? null,
        ]);

        return $this->successResponse(
            $user->load('profile'),
            'Account information updated successfully'
        );
    }

    /**
     * Secure and isolated password modification endpoint
     * Route: POST /api/auth/profile/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed', // Requires 'new_password_confirmation' from frontend
        ]);

        // Validate the historical password mapping match
        if (!Hash::check($validated['old_password'], $user->password)) {
            return $this->errorResponse('The current password you entered is incorrect', 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password'])
        ]);

        return $this->successResponse([], 'Password changed successfully');
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'client') {
            return response()->json(['message' => 'This function is only available for client accounts.'], 403);
        }

        try {
            $user->workgroups()->detach();
            $user->orders()->delete();
            $user->favorites()->delete();
            $user->fcmTokens()->delete();
            $user->notifications()->delete();

            if ($user->profile) {
                $user->profile()->delete();
            }
            $user->delete();

            return $this->successResponse([], 'Your account has been deleted successfully.',200);

        } catch (\Exception $e) {
            \Log::error('Error deleting client account: ' . $e->getMessage(), ['user_id' => $user->id]);
            return $this->errorResponse('An error occurred while trying to delete your account. Please try again later.', 500);
        }
    }
}
