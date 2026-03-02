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

    /**
     * Get authenticated user's profile
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();

            // Load interests relationship
            $user->load('interests');

            $profileData = [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'dob' => $user->dob?->format('Y-m-d'),
                'bio' => $user->bio,
                'about' => $user->about,
                'account_type' => $user->account_type,
                'kyc_verified' => $user->kyc_verified,
                'kyc_verified_at' => $user->kyc_verified_at,
                'profile_picture' => $user->profile_picture ? Storage::url($user->profile_picture) : null,
                'intro_video' => $user->intro_video ? Storage::url($user->intro_video) : null,
                'interests' => $user->interests->map(function ($interest) {
                    return [
                        'id' => $interest->id,
                        'name' => $interest->name,
                    ];
                }),
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ];

            return $this->successResponse(
                'Profile retrieved successfully',
                $profileData,
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Update authenticated user's profile
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();

            // Validate user input
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

            // Update user profile
            $user->fill($validated);
            $user->save();

            // Sync interests if provided
            if (isset($validated['interests'])) {
                $user->interests()->sync($validated['interests']);
            }

            // Reload interests
            $user->load('interests');

            $profileData = [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'address' => $user->address,
                'dob' => $user->dob?->format('Y-m-d'),
                'bio' => $user->bio,
                'about' => $user->about,
                'interests' => $user->interests->map(function ($interest) {
                    return [
                        'id' => $interest->id,
                        'name' => $interest->name,
                    ];
                }),
            ];

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
