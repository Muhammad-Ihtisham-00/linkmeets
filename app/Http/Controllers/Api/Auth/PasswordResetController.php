<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Models\PasswordResetCode;
use App\Mail\PasswordResetCodeMail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    use ApiResponse;

    /**
     * Step 1: Send password reset code to user's email
     */
    public function sendResetCode(Request $request)
    {
        try {

            // Validate input
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $email = $validated['email'];

            // Find user and generate code
            $user = User::where('email', $email)->first();
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Remove old codes
            PasswordResetCode::deleteAllForEmail($email);

            // Store new code
            PasswordResetCode::create([
                'email'      => $email,
                'code'       => Hash::make($code),
                'created_at' => now(),
            ]);

            // Send code email
            Mail::to($email)->send(new PasswordResetCodeMail($code, $user));

            // Response
            return $this->successResponse('Password reset code sent to your email');
        } catch (ValidationException $e) {

            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {

            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }


    /**
     * Step 2: Verify the reset code
     */
    public function verifyResetCode(Request $request)
    {
        try {

            // Validate input
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'code'  => 'required|string|size:6',
            ]);

            $email = $validated['email'];
            $code  = $validated['code'];

            // Fetch reset record
            $resetRecord = PasswordResetCode::where('email', $email)->first();

            // Check if exists
            if (!$resetRecord) {
                return $this->errorResponse('Invalid or expired reset code');
            }

            // Check expiry
            if ($resetRecord->isExpired()) {
                $resetRecord->delete();
                return $this->errorResponse('Reset code has expired. Please request a new one');
            }

            // Validate code
            if (!Hash::check($code, $resetRecord->code)) {
                return $this->errorResponse('Invalid reset code');
            }

            // Response
            return $this->successResponse('Reset code verified successfully');
        } catch (ValidationException $e) {

            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {

            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }


    /**
     * Step 3: Reset password
     */
    public function resetPassword(Request $request)
    {
        try {

            // Validate input
            $validated = $request->validate([
                'email'    => 'required|email|exists:users,email',
                'code'     => 'required|string|size:6',
                'password' => ['required', 'confirmed', Password::min(8)],
            ]);

            $email = $validated['email'];
            $code  = $validated['code'];

            // Fetch reset record
            $resetRecord = PasswordResetCode::where('email', $email)->first();

            // Check if exists
            if (!$resetRecord) {
                return $this->errorResponse('Invalid or expired reset code');
            }

            // Check expiry
            if ($resetRecord->isExpired()) {
                $resetRecord->delete();
                return $this->errorResponse('Reset code has expired. Please request a new one');
            }

            // Validate code
            if (!Hash::check($code, $resetRecord->code)) {
                return $this->errorResponse('Invalid reset code');
            }

            // Update user password
            $user = User::where('email', $email)->first();
            $user->password = Hash::make($validated['password']);
            $user->save();

            // Remove code + revoke tokens
            $resetRecord->delete();
            $user->tokens()->delete();

            // Response
            return $this->successResponse('Password has been reset successfully');
        } catch (ValidationException $e) {

            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {

            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }
}
