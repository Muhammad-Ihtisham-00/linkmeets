<?php

// Controller - ChangePasswordController.php
namespace App\Http\Controllers\Api\Profile;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordController extends Controller
{
    use ApiResponse;

    /**
     * Change user password
     */
    public function change(Request $request)
    {
        try {
            // Validate user input
            $validated = $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = $request->user();

            // Check if current password is correct
            if (!Hash::check($validated['current_password'], $user->password)) {
                return $this->errorResponse(
                    'Validation failed',
                    ['current_password' => ['Current password is incorrect']],
                    422
                );
            }

            // Update password
            $user->password = Hash::make($validated['password']);
            $user->save();

            return $this->successResponse(
                'Password changed successfully',
                null,
                200
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }
}
