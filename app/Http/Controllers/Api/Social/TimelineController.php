<?php

namespace App\Http\Controllers\Api\Social;

use App\Models\Post;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Models\UserRelationship;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class TimelineController extends Controller
{
    use ApiResponse;

    /**
     * Get user timeline (posts from people the user follows)
     */
    public function home(Request $request)
    {
        try {

            $user = $request->user();

            $sort = $request->get('sort', 'newest');

            // Get users I follow
            $followingIds = UserRelationship::where('user_id', $user->id)
                ->where('type', UserRelationship::FOLLOW)
                ->pluck('related_user_id');

            // Base query
            $query = Post::with(['user', 'media'])
                ->withCount(['likes', 'comments', 'shares'])
                ->withExists([
                    'likes as liked_by_me' => function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    },
                    'shares as shared_by_me' => function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    }
                ])
                ->whereIn('user_id', $followingIds)
                ->where('visibility', 'public');

            // Sorting
            if ($sort === 'most_liked') {
                $query->orderBy('likes_count', 'desc');
            } elseif ($sort === 'most_viewed') {
                $query->orderBy('views_count', 'desc');
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $posts = $query->paginate(10);

            $posts->getCollection()->transform(function ($post) {

                $media = $post->media->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'file' => Storage::url($m->file_path),
                        'media_type' => $m->media_type,
                    ];
                });

                return [
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
                    'shares_count' => $post->shares_count,
                    'shared_by_me' => (bool) $post->shared_by_me,
                    'media' => $media,
                    'created_at' => $post->created_at,
                ];
            });

            return $this->successResponse(
                'Timeline retrieved successfully',
                $posts,
                200
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
