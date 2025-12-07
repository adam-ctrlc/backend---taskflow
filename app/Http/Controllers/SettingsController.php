<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class SettingsController extends Controller
{
    public function index(Request $request, User $user)
    {
        try {
            $updatedFields = [];

            // Handle base64 profile picture
            if ($request->filled('profile_picture_base64')) {
                // Delete old file if exists
                if ($user->profile_picture) {
                    Storage::disk('public')->delete($user->profile_picture);
                }

                $user->profile_picture = null;
                $user->profile_picture_base64 = $request->input('profile_picture_base64');
                $user->profile_picture_mime_type = $request->input('profile_picture_mime_type');
                $updatedFields['profile_picture'] = 'base64_image';
            }

            if ($request->has('name')) {
                $user->name = $request->input('name');
                $updatedFields['name'] = $user->name;
            }

            if ($request->has('email')) {
                $user->email = $request->input('email');
                $updatedFields['email'] = $user->email;
            }

            if (!empty($updatedFields)) {
                $user->save();

                // Reload user to get updated data
                $user->refresh();

                return response()->json([
                    'message' => 'Profile updated successfully.',
                    'updated' => $updatedFields,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'profile_picture' => $user->profile_picture ?? null,
                        'profile_picture_base64' => $user->profile_picture_base64 ?? null,
                        'profile_picture_mime_type' => $user->profile_picture_mime_type ?? null
                    ]
                ]);
            }

            return response()->json(['message' => 'No changes made.'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating profile: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changePassword(Request $request, User $user)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'errors' => ['current_password' => ['Current password is incorrect.']]
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.'
        ]);
    }
}
