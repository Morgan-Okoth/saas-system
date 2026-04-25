<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * API Login Controller
 * 
 * Handles mobile app authentication with Sanctum tokens.
 * All requests require school_id header for tenant context.
 */
class LoginController extends Controller
{
    /**
     * Login with email/password
     */
    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'school_id' => ['required', 'exists:schools,id'],
        ]);

        // Find user in school
        $user = User::where('email', $request->email)
            ->where('school_id', $request->school_id)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid credentials',
                'code' => 'invalid_credentials',
            ], 401);
        }

        // Check if school is active
        if ($user->school->isExpired()) {
            return response()->json([
                'error' => 'Subscription expired',
                'code' => 'subscription_expired',
                'message' => 'Please renew your subscription',
            ], 403);
        }

        // Create token
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userData($user),
            'school' => $this->schoolData($user->school),
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        // Delete old tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userData($user),
        ]);
    }

    /**
     * Get user profile
     */
    public function profile(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'user' => $this->userData($request->user()),
            'school' => $this->schoolData($request->user()->school),
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
        ]);

        $request->user()->update($request->only(['name', 'email']));

        return response()->json([
            'user' => $this->userData($request->user()),
            'message' => 'Profile updated successfully',
        ]);
    }

    /**
     * Forgot password
     */
    public function forgotPassword(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'school_id' => ['required', 'exists:schools,id'],
        ]);

        $user = User::where('email', $request->email)
            ->where('school_id', $request->school_id)
            ->first();

        if ($user) {
            // TODO: Send password reset notification via Resend
            \Log::info('Password reset requested', [
                'school_id' => $user->school_id,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        // Always return success to prevent email enumeration
        return response()->json([
            'message' => 'If an account exists, a password reset link has been sent',
        ]);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'school_id' => ['required', 'exists:schools,id'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // TODO: Verify reset token
        $user = User::where('email', $request->email)
            ->where('school_id', $request->school_id)
            ->first();

        if (!$user) {
            return response()->json([
                'error' => 'Invalid reset request',
                'code' => 'invalid_token',
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Delete all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully',
        ]);
    }

    /**
     * Format user data for API response
     */
    protected function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    /**
     * Format school data for API response
     */
    protected function schoolData(School $school): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'email' => $school->email,
            'subscription_status' => $school->subscription_status,
        ];
    }
}
