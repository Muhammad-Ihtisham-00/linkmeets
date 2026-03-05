<?php

namespace App\Http\Controllers\Api\Social;

use App\Models\Post;
use App\Models\PostMedia;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    use ApiResponse;

    /**
     * Create a new post (text + optional media)
     */
    public function create(Request $request)
    {
        try {
            $validated = $request->validate([
                'content' => 'nullable|string|max:5000',
                'visibility' => 'required|in:public,private',
                'media' => 'nullable|array|max:4',
                'media.*.file' => 'required|file|mimes:jpg,jpeg,png,webp,mp4,pdf,doc,docx|max:51200', // 50MB max
                'media.*.media_type' => 'required|in:image,video,document',
            ]);

            $user = $request->user();

            // Step 1: Create post
            $post = Post::create([
                'user_id' => $user->id,
                'content' => $validated['content'] ?? null,
                'visibility' => $validated['visibility'],
                'type' => 'standard',
            ]);

            $uploadedMedia = [];

            // Step 2: Handle media
            if (!empty($validated['media'])) {
                foreach ($validated['media'] as $mediaItem) {

                    $file = $mediaItem['file'];
                    $mediaType = $mediaItem['media_type'];

                    $path = $file->store('posts');

                    $postMedia = PostMedia::create([
                        'post_id' => $post->id,
                        'file_path' => $path,
                        'media_type' => $mediaType,
                    ]);

                    $uploadedMedia[] = [
                        'id' => $postMedia->id,
                        'file' => Storage::url($postMedia->file_path),
                        'media_type' => $postMedia->media_type,
                    ];
                }
            }

            return $this->successResponse(
                'Post created successfully',
                [
                    'post_id' => $post->id,
                    'content' => $post->content,
                    'visibility' => $post->visibility,
                    'media' => $uploadedMedia,
                    'created_at' => $post->created_at,
                ],
                201
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Show a single post
     */
    public function show(Request $request, $postId)
    {
        try {
            $user = $request->user();

            // Fetch the post with media, user, counts, and flags
            $post = Post::with('user', 'media')
                ->withCount(['likes', 'comments', 'shares']) // added shares
                ->withExists([
                    'likes as liked_by_me' => function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    },
                    'shares as shared_by_me' => function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    }
                ])
                ->findOrFail($postId);

            // Check visibility
            if ($post->visibility === 'private' && $post->user_id !== $user->id) {
                return $this->errorResponse('This post is private', null, 403);
            }

            // Increment views if not owner
            if ($post->user_id !== $user->id) {
                $post->increment('views_count');
            }

            // Prepare media
            $media = $post->media->map(function ($m) {
                return [
                    'id' => $m->id,
                    'file' => Storage::url($m->file_path),
                    'media_type' => $m->media_type,
                ];
            });

            // Prepare response
            $data = [
                'post_id' => $post->id,
                'user' => [
                    'id' => $post->user->id,
                    'first_name' => $post->user->first_name,
                    'last_name' => $post->user->last_name,
                    'username' => $post->user->username,
                    'profile_picture' => $post->user->profile_picture
                        ? Storage::url($post->user->profile_picture)
                        : null,
                ],
                'content' => $post->content,
                'visibility' => $post->visibility,
                'views_count' => $post->views_count,
                'likes_count' => $post->likes_count,
                'liked_by_me' => (bool) $post->liked_by_me,
                'comments_count' => $post->comments_count,
                'shares_count' => $post->shares_count,      // ✅ added
                'shared_by_me' => (bool) $post->shared_by_me, // ✅ added
                'media' => $media,
                'created_at' => $post->created_at,
            ];

            return $this->successResponse('Post retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Delete a post
     */
    public function delete(Request $request, $postId)
    {
        try {
            $user = $request->user();

            $post = Post::where('id', $postId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Delete media from storage
            foreach ($post->media as $media) {
                Storage::delete($media->file_path);
                $media->delete();
            }

            $post->delete();

            return $this->successResponse('Post deleted successfully', null, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Increment post views
     */
    public function incrementView(Request $request, $postId)
    {
        try {
            $post = Post::findOrFail($postId);

            $post->increment('views_count');

            return $this->successResponse(
                'Success',
                ['views_count' => $post->views_count],
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function toggleLike(Request $request, $postId)
    {
        try {
            $user = $request->user();

            $post = Post::findOrFail($postId);

            $like = $post->likes()
                ->where('user_id', $user->id)
                ->first();

            if ($like) {
                $like->delete();
                $liked = false;
            } else {
                $post->likes()->create([
                    'user_id' => $user->id
                ]);
                $liked = true;
            }

            return $this->successResponse(
                $liked ? 'Post liked' : 'Post unliked',
                [
                    'liked' => $liked,
                    'likes_count' => $post->likes()->count()
                ],
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function likes(Request $request, $postId)
    {
        try {
            $post = Post::with([
                'likedByUsers:id,first_name,last_name,username,profile_picture'
            ])->findOrFail($postId);

            $users = $post->likedByUsers->map(function ($u) {
                return [
                    'id' => $u->id,
                    'first_name' => $u->first_name,
                    'last_name' => $u->last_name,
                    'username' => $u->username,
                    'profile_picture' => $u->profile_picture
                        ? Storage::url($u->profile_picture)
                        : null,
                ];
            });

            return $this->successResponse(
                'Post likes retrieved',
                [
                    'likes_count' => $post->likedByUsers->count(),
                    'users' => $users
                ],
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function share(Request $request, $postId)
    {
        try {
            $user = $request->user();

            $request->validate([
                'caption' => 'nullable|string|max:1000'
            ]);

            $post = Post::findOrFail($postId);

            // Prevent duplicate share
            if ($post->shares()->where('user_id', $user->id)->exists()) {
                return $this->errorResponse('You already shared this post', null, 400);
            }

            $post->shares()->create([
                'user_id' => $user->id,
                'caption' => $request->caption
            ]);

            return $this->successResponse('Post shared successfully', null, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }
}
 