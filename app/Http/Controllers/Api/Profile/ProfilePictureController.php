<?php

namespace App\Http\Controllers\Api\Profile;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfilePictureController extends Controller
{
    use ApiResponse;

    public function upload(Request $request)
    {
        try {

            $validated = $request->validate([
                'profile_picture' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            $user = $request->user();

            // Delete old profile picture if exists
            if ($user->profile_picture) {
                Storage::delete($user->profile_picture);
            }

            // Upload new profile picture
            $profilePicture = $request->file('profile_picture')
                ? $request->file('profile_picture')->store('profile_pictures')
                : null;
            $user->profile_picture = $profilePicture;
            $user->save();

            return $this->successResponse(
                'Profile picture uploaded successfully',
                [
                    'profile_picture' => Storage::url($user->profile_picture),
                ],
                200
            );
        } catch (ValidationException $e) {

            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {

            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }
}
