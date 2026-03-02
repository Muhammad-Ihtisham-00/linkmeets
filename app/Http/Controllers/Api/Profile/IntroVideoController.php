<?php

namespace App\Http\Controllers\Api\Profile;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class IntroVideoController extends Controller
{
    use ApiResponse;

    // Upload/Update intro video
    public function upload(Request $request)
    {
        try {
            // Adjust max size if needed
            // Available sizes: 10MB=10240, 20MB=20480, 30MB=30720, 40MB=40960, 50MB=51200, 100MB=102400
            $validated = $request->validate([
                'intro_video' => 'required|mimes:mp4,mov,avi,wmv,flv,mkv|max:20480',
            ]);

            $user = $request->user();

            // Delete old intro video if exists
            if ($user->intro_video) {
                Storage::delete($user->intro_video);
            }

            // Upload new intro video
            $introVideo = $request->file('intro_video')
                ? $request->file('intro_video')->store('intro_videos')
                : null;

            $user->intro_video = $introVideo;
            $user->save();

            return $this->successResponse(
                'Intro video uploaded successfully',
                [
                    'intro_video' => Storage::url($user->intro_video),
                ],
                200
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    // Get intro video
    public function show(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->intro_video) {
                return $this->errorResponse('No intro video found', null, 404);
            }

            return $this->successResponse(
                'Intro video retrieved successfully',
                [
                    'intro_video' => Storage::url($user->intro_video),
                ],
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    // Delete intro video
    public function delete(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->intro_video) {
                return $this->errorResponse('No intro video to delete', null, 404);
            }

            // Delete file from storage
            Storage::delete($user->intro_video);

            // Remove path from database
            $user->intro_video = null;
            $user->save();

            return $this->successResponse(
                'Intro video deleted successfully',
                null,
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }
}
