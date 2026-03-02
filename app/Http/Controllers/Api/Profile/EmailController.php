<?php

namespace App\Http\Controllers\Api\Profile;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\ValidationException;

class EmailController extends Controller
{
    use ApiResponse;

    /**
     * Change email and mark as unverified
     */
    public function change(Request $request)
    {
        try {
            // Validate user input
            $validated = $request->validate([
                'email' => 'required|email|max:255|unique:users,email,' . $request->user()->id,
            ]);

            $user = $request->user();
            $oldEmail = $user->email;

            // Update email and mark as unverified
            $user->email = $validated['email'];
            $user->email_verified_at = null;
            $user->save();

            // Send verification email to new email address
            event(new Registered($user));

            return $this->successResponse(
                'Email changed successfully. Please verify your new email address',
                [
                    'old_email' => $oldEmail,
                    'new_email' => $user->email,
                    'email_verified' => false,
                    'message' => 'A verification link has been sent to your new email address',
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
