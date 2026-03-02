<?php

// Controller - UsernameController.php
namespace App\Http\Controllers\Api\Profile;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class UsernameController extends Controller
{
    use ApiResponse;

    /**
     * Check if username is available (live validation)
     */
    public function checkAvailability(Request $request)
    {
        try {
            // Validate user input
            $validated = $request->validate([
                'username' => 'required|string|max:255',
            ]);

            $currentUser = $request->user();
            $username = $validated['username'];

            // Check if username exists (excluding current user)
            $exists = User::where('username', $username)
                ->where('id', '!=', $currentUser->id)
                ->exists();

            if ($exists) {
                return $this->successResponse(
                    'Username is not available',
                    [
                        'available' => false,
                        'username' => $username,
                    ],
                    200
                );
            }

            return $this->successResponse(
                'Username is available',
                [
                    'available' => true,
                    'username' => $username,
                ],
                200
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Change username
     */
    public function change(Request $request)
    {
        try {
            // Validate user input
            $validated = $request->validate([
                'username' => 'required|string|max:255|unique:users,username,' . $request->user()->id,
            ]);

            $user = $request->user();
            $oldUsername = $user->username;

            // Update username
            $user->username = $validated['username'];
            $user->save();

            return $this->successResponse(
                'Username changed successfully',
                [
                    'old_username' => $oldUsername,
                    'new_username' => $user->username,
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
