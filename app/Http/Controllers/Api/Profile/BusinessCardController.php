<?php

namespace App\Http\Controllers\Api\Profile;

use App\Models\BusinessCard;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class BusinessCardController extends Controller
{
    use ApiResponse;

    /**
     * Get business card
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();

            // Get or create business card
            $businessCard = BusinessCard::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'email' => null,
                    'phone' => null,
                    'website' => null,
                    'office_address' => null,
                    'facebook' => null,
                    'twitter' => null,
                    'instagram' => null,
                    'youtube' => null,
                ]
            );

            return $this->successResponse(
                'Business card retrieved successfully',
                [
                    'id' => $businessCard->id,
                    'email' => $businessCard->email,
                    'phone' => $businessCard->phone,
                    'website' => $businessCard->website,
                    'office_address' => $businessCard->office_address,
                    'facebook' => $businessCard->facebook,
                    'twitter' => $businessCard->twitter,
                    'instagram' => $businessCard->instagram,
                    'youtube' => $businessCard->youtube,
                    'created_at' => $businessCard->created_at,
                    'updated_at' => $businessCard->updated_at,
                ],
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Update business card
     */
    public function update(Request $request)
    {
        try {
            // Validate user input
            $validated = $request->validate([
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
                'website' => 'nullable|url|max:255',
                'office_address' => 'nullable|string|max:500',
                'facebook' => 'nullable|url|max:255',
                'twitter' => 'nullable|url|max:255',
                'instagram' => 'nullable|url|max:255',
                'youtube' => 'nullable|url|max:255',
            ]);

            $user = $request->user();

            // Update or create business card
            $businessCard = BusinessCard::updateOrCreate(
                ['user_id' => $user->id],
                $validated
            );

            return $this->successResponse(
                'Business card updated successfully',
                [
                    'id' => $businessCard->id,
                    'email' => $businessCard->email,
                    'phone' => $businessCard->phone,
                    'website' => $businessCard->website,
                    'office_address' => $businessCard->office_address,
                    'facebook' => $businessCard->facebook,
                    'twitter' => $businessCard->twitter,
                    'instagram' => $businessCard->instagram,
                    'youtube' => $businessCard->youtube,
                    'updated_at' => $businessCard->updated_at,
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
