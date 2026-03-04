<?php

namespace App\Http\Controllers\Api\Social;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostCommentLike;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PostCommentController extends Controller
{
    use ApiResponse;

    /**
     * Add comment or reply
     */
    public function store(Request $request, $postId)
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string|max:2000',
                'parent_id' => 'nullable|exists:post_comments,id'
            ]);

            $user = $request->user();

            $post = Post::findOrFail($postId);

            $comment = PostComment::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'parent_id' => $validated['parent_id'] ?? null,
                'content' => $validated['content'],
            ]);

            return $this->successResponse(
                'Comment added successfully',
                [
                    'comment_id' => $comment->id,
                    'content' => $comment->content,
                    'parent_id' => $comment->parent_id,
                    'created_at' => $comment->created_at,
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
     * Get comments with replies
     */
    public function index(Request $request, $postId)
    {
        try {
            $user = $request->user();

            $comments = PostComment::with([
                'user:id,first_name,last_name,username,profile_picture'
            ])
                ->withCount(['likes', 'replies'])
                ->withExists([
                    'likes as liked_by_me' => fn($q) => $q->where('user_id', $user->id)
                ])
                ->where('post_id', $postId)
                ->whereNull('parent_id')
                ->latest()
                ->paginate(10);

            $data = $comments->through(function ($comment) {

                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'likes_count' => $comment->likes_count,
                    'liked_by_me' => (bool) $comment->liked_by_me,
                    'replies_count' => $comment->replies_count,
                    'user' => [
                        'id' => $comment->user->id,
                        'first_name' => $comment->user->first_name,
                        'last_name' => $comment->user->last_name,
                        'username' => $comment->user->username,
                        'profile_picture' => $comment->user->profile_picture
                            ? Storage::url($comment->user->profile_picture)
                            : null,
                    ],
                    'created_at' => $comment->created_at,
                ];
            });

            return $this->successResponse(
                'Comments retrieved successfully',
                $data,
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Toggle comment like
     */
    public function toggleLike(Request $request, $commentId)
    {
        try {
            $user = $request->user();

            $comment = PostComment::findOrFail($commentId);

            $like = $comment->likes()
                ->where('user_id', $user->id)
                ->first();

            if ($like) {
                $like->delete();
                $liked = false;
            } else {
                $comment->likes()->create([
                    'user_id' => $user->id
                ]);
                $liked = true;
            }

            return $this->successResponse(
                $liked ? 'Comment liked' : 'Comment unliked',
                [
                    'liked' => $liked,
                    'likes_count' => $comment->likes()->count()
                ],
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Delete comment
     */
    public function delete(Request $request, $commentId)
    {
        try {
            $user = $request->user();

            $comment = PostComment::where('id', $commentId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $comment->delete();

            return $this->successResponse(
                'Comment deleted successfully',
                null,
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function replies(Request $request, $commentId)
    {
        try {
            $user = $request->user();

            $replies = PostComment::with([
                'user:id,first_name,last_name,username,profile_picture'
            ])
                ->withCount(['likes', 'replies'])
                ->withExists([
                    'likes as liked_by_me' => fn($q) =>
                    $q->where('user_id', $user->id)
                ])
                ->where('parent_id', $commentId)
                ->latest()
                ->paginate(10);

            $data = $replies->through(function ($reply) {

                return [
                    'id' => $reply->id,
                    'content' => $reply->content,
                    'likes_count' => $reply->likes_count,
                    'liked_by_me' => (bool) $reply->liked_by_me,
                    'replies_count' => $reply->replies_count,
                    'user' => [
                        'id' => $reply->user->id,
                        'first_name' => $reply->user->first_name,
                        'last_name' => $reply->user->last_name,
                        'username' => $reply->user->username,
                        'profile_picture' => $reply->user->profile_picture
                            ? Storage::url($reply->user->profile_picture)
                            : null,
                    ],
                    'created_at' => $reply->created_at,
                ];
            });

            return $this->successResponse(
                'Replies retrieved successfully',
                $data,
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function likes(Request $request, $commentId)
    {
        try {
            $comment = PostComment::with([
                'likedByUsers:id,first_name,last_name,username,profile_picture'
            ])->findOrFail($commentId);

            $users = $comment->likedByUsers->map(function ($u) {
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
                'Comment likes retrieved',
                [
                    'likes_count' => $comment->likedByUsers->count(),
                    'users' => $users
                ],
                200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }
}
