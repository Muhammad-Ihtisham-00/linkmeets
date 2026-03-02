<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerificationController extends Controller
{
    use ApiResponse;

    public function verify($id, $hash, Request $request)
    {
        // Find user
        $user = User::find($id);

        // Check if user exists
        if (!$user) {
            return $this->errorResponse('Invalid verification link.', null, 404);
        }

        // Validate hash
        if (!hash_equals(sha1($user->email), $hash)) {
            return $this->errorResponse('Invalid verification link.', null, 403);
        }

        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            return $this->successResponse('Email already verified.');
        }

        // Mark as verified
        $user->markEmailAsVerified();

        // Web response after verification
        return response()->view('verification.verify-success');
    }

    public function resend(Request $request)
    {
        // Check auth
        if (!$request->user()) {
            return $this->errorResponse('Unauthorized', null, 401);
        }

        // Skip if already verified
        if ($request->user()->hasVerifiedEmail()) {
            return $this->successResponse('Email already verified.');
        }

        // Send verification link
        $request->user()->sendEmailVerificationNotification();

        // Response
        return $this->successResponse('Verification link sent!');
    }
}
