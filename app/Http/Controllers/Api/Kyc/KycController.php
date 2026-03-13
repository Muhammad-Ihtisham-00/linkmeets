<?php

namespace App\Http\Controllers\Api\Kyc;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Models\KycVerification;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KycController extends Controller
{
    use ApiResponse;

    /**
     * Submit KYC
     */
    public function submit(Request $request)
    {
        try {

            $validated = $request->validate([
                'identity_card' => 'required|image|mimes:jpg,jpeg,png,webp|max:20480',
                'selfie' => 'required|image|mimes:jpg,jpeg,png,webp|max:20480',
            ]);

            $user = $request->user();

            $identityPath = $request->file('identity_card')->store('kyc');
            $selfiePath = $request->file('selfie')->store('kyc');

            $kyc = KycVerification::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'identity_card' => $identityPath,
                    'selfie' => $selfiePath
                ]
            );

            return $this->successResponse(
                'KYC submitted successfully',
                [
                    'identity_card' => Storage::url($kyc->identity_card),
                    'selfie' => Storage::url($kyc->selfie)
                ],
                201
            );
        } catch (ValidationException $e) {

            return $this->errorResponse(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Throwable $e) {

            return $this->errorResponse(
                'Something went wrong',
                $e->getMessage(),
                500
            );
        }
    }
}
