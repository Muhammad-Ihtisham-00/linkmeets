<?php

namespace App\Http\Controllers\Api\Profile;

use App\Models\Gallery;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GalleryController extends Controller
{
    use ApiResponse;

    /**
     * Get all gallery images for user
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $images = Gallery::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($gallery) {
                    return [
                        'id' => $gallery->id,
                        'image' => Storage::url($gallery->image),
                        'created_at' => $gallery->created_at,
                    ];
                });

            return $this->successResponse(
                'Gallery images retrieved successfully',
                $images,
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }


    /**
     * Upload single or multiple images to gallery
     */
    public function upload(Request $request)
    {
        try {
            $validated = $request->validate([
                'images' => 'required|array|max:10',
                'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:20480',
            ]);

            $user = $request->user();
            $uploaded = [];

            foreach ($request->file('images') as $image) {

                $path = $image->store('gallery');

                $gallery = Gallery::create([
                    'user_id' => $user->id,
                    'image' => $path,
                ]);

                $uploaded[] = [
                    'id' => $gallery->id,
                    'image' => Storage::url($gallery->image),
                    'created_at' => $gallery->created_at,
                ];
            }

            return $this->successResponse(
                'Images uploaded successfully',
                $uploaded,
                201
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }


    /**
     * Delete single or multiple images from gallery
     */
    public function delete(Request $request)
    {
        try {
            // Step 1: validate IDs exist in table (generic)
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|integer|exists:galleries,id',
            ]);

            $user = $request->user();

            // Step 2: normal flow: fetch only user's images
            $galleries = Gallery::where('user_id', $user->id)
                ->whereIn('id', $validated['ids'])
                ->get();

            foreach ($galleries as $gallery) {
                Storage::delete($gallery->image);
                $gallery->delete();
            }

            return $this->successResponse(
                'Images deleted successfully',
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
