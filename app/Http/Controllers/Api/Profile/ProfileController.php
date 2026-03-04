<?php

namespace App\Http\Controllers\Api\Profile;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    use ApiResponse;

    public function show(Request $request)
    {
        try {

            $user = $request->user();
            $user->load('interests');

            $profileData = $user->toArray();

            $profileData['profile_picture'] = $user->profile_picture
                ? Storage::url($user->profile_picture)
                : null;

            $profileData['intro_video'] = $user->intro_video
                ? Storage::url($user->intro_video)
                : null;

            return $this->successResponse(
                'Profile retrieved successfully',
                $profileData,
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function update(Request $request)
    {
        try {

            $user = $request->user();

            $validated = $request->validate([
                'first_name' => 'sometimes|string|max:50',
                'last_name' => 'sometimes|string|max:50',
                'phone' => 'sometimes|string|max:20',
                'address' => 'sometimes|string|max:500',
                'dob' => 'sometimes|date|before:today',
                'bio' => 'sometimes|string|max:500',
                'about' => 'sometimes|string|max:2000',
                'interests' => 'sometimes|array',
                'interests.*' => 'exists:interests,id',
            ]);

            $user->fill($validated);
            $user->save();

            if (isset($validated['interests'])) {
                $user->interests()->sync($validated['interests']);
            }

            $user->load('interests');

            $profileData = $user->toArray();

            $profileData['profile_picture'] = $user->profile_picture
                ? Storage::url($user->profile_picture)
                : null;

            $profileData['intro_video'] = $user->intro_video
                ? Storage::url($user->intro_video)
                : null;

            return $this->successResponse(
                'Profile updated successfully',
                $profileData,
                200
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }
}
