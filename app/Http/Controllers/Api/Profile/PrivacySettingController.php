<?php

namespace App\Http\Controllers\Api\Profile;

use App\Models\PrivacySetting;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class PrivacySettingController extends Controller
{
    use ApiResponse;

    /**
     * Get privacy settings
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();

            // Get or create privacy settings with defaults
            $privacySettings = PrivacySetting::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'profile_private' => false,
                    'allow_comments' => true,
                    'allow_tagging' => true,
                    'post_visibility' => 1,
                    'email_visibility' => 1,
                    'phone_visibility' => 1,
                ]
            );

            return $this->successResponse(
                'Privacy settings retrieved successfully',
                [
                    'id' => $privacySettings->id,
                    'profile_private' => $privacySettings->profile_private,
                    'allow_comments' => $privacySettings->allow_comments,
                    'allow_tagging' => $privacySettings->allow_tagging,
                    'post_visibility' => $privacySettings->post_visibility,
                    'email_visibility' => $privacySettings->email_visibility,
                    'phone_visibility' => $privacySettings->phone_visibility,
                    'created_at' => $privacySettings->created_at,
                    'updated_at' => $privacySettings->updated_at,
                ],
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Update privacy settings
     */
    public function update(Request $request)
    {
        try {
            // Validate user input
            $validated = $request->validate([
                'profile_private' => 'sometimes|boolean',
                'allow_comments' => 'sometimes|boolean',
                'allow_tagging' => 'sometimes|boolean',
                'post_visibility' => 'sometimes|integer|in:1,2,3',
                'email_visibility' => 'sometimes|integer|in:1,2,3',
                'phone_visibility' => 'sometimes|integer|in:1,2,3',
            ]);

            $user = $request->user();

            // Update or create privacy settings
            $privacySettings = PrivacySetting::updateOrCreate(
                ['user_id' => $user->id],
                $validated
            );

            return $this->successResponse(
                'Privacy settings updated successfully',
                [
                    'id' => $privacySettings->id,
                    'profile_private' => $privacySettings->profile_private,
                    'allow_comments' => $privacySettings->allow_comments,
                    'allow_tagging' => $privacySettings->allow_tagging,
                    'post_visibility' => $privacySettings->post_visibility,
                    'email_visibility' => $privacySettings->email_visibility,
                    'phone_visibility' => $privacySettings->phone_visibility,
                    'created_at' => $privacySettings->created_at,
                    'updated_at' => $privacySettings->updated_at,
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
