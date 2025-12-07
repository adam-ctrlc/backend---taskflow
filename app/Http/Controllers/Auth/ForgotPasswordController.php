<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    /**
     * Verify user credentials (username and email) for password reset
     */
    public function verifyUser(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        // Find user by username (name field) and email
        $user = User::where('name', $request->username)
                   ->where('email', $request->email)
                   ->first();

        if (!$user) {
            return response()->json([
                'message' => 'No user found with the provided username and email combination.',
                'verified' => false
            ], 404);
        }

        // Generate a simple token for password reset (you might want to use a more secure approach)
        $token = bin2hex(random_bytes(32));
        
        // Store the token temporarily (in a real app, you'd store this in a password_resets table)
        // For now, we'll return it directly
        return response()->json([
            'message' => 'User verified successfully. You can now reset your password.',
            'verified' => true,
            'reset_token' => $token,
            'user_id' => $user->id
        ], 200);
    }

    /**
     * Reset password with verification
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Find user by username and email again for security
        $user = User::where('name', $request->username)
                   ->where('email', $request->email)
                   ->first();

        if (!$user) {
            return response()->json([
                'message' => 'No user found with the provided username and email combination.',
            ], 404);
        }

        // Update the password
        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        return response()->json([
            'message' => 'Password has been reset successfully. You can now login with your new password.'
        ], 200);
    }
}