<?php

namespace App\Http\Controllers\Api\Profile;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function showUser($userId)
    {
        try {

            $user = User::with('interests')->findOrFail($userId);

            $profileData = $user->toArray();

            $profileData['profile_picture'] = $user->profile_picture
                ? Storage::url($user->profile_picture)
                : null;

            $profileData['intro_video'] = $user->intro_video
                ? Storage::url($user->intro_video)
                : null;

            return $this->successResponse(
                'User profile retrieved successfully',
                $profileData,
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function deleteProfile(Request $request)
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            // 1️⃣ Delete related data

            // Posts, comments, likes, shares
            $user->posts()->delete();
            $user->comments()->delete();
            $user->commentLikes()->delete();
            $user->sharedPosts()->detach();

            // Marketplace products
            $user->marketplaceProducts()->delete();

            // Services & Appointments
            $user->services()->delete();
            $user->bookedAppointments()->delete();
            $user->receivedAppointments()->delete();

            // Reviews
            $user->givenReviews()->delete();
            $user->receivedReviews()->delete();

            // Conversations & messages
            $user->sentMessages()->delete();
            $user->conversations()->detach();
            $user->createdGroups()->delete();

            // Blocks and reports
            $user->blockedUsers()->delete();
            $user->blockedByUsers()->delete();
            $user->reportsMade()->delete();
            $user->reportsReceived()->delete();

            // Interests
            $user->interests()->detach();

            // KYC, business card, privacy settings, galleries
            $user->kycVerification()->delete();
            $user->businessCard()->delete();
            $user->privacySettings()->delete();
            $user->galleries()->delete();

            // 2️⃣ Delete user
            $user->delete();
        });

        return $this->successResponse('Profile deleted successfully');
    }
}
