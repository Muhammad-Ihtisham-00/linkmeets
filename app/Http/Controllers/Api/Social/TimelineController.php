<?php

namespace App\Http\Controllers\Api\Social;

use App\Models\Post;
use App\Traits\ApiResponse;
use App\Models\PostPollVote;
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

            // Users I follow
            $followingIds = UserRelationship::where('user_id', $user->id)
                ->where('type', UserRelationship::FOLLOW)
                ->pluck('related_user_id');

            // Base query
            $query = Post::with([
                'user',
                'media',
                'celebration',
                'hiring',
                'jobseeker',
                'findExpert',
                'event',
                'poll.options' // load poll options
            ])
                ->withCount(['likes', 'comments', 'shares'])
                ->withExists([
                    'likes as liked_by_me' => fn($q) => $q->where('user_id', $user->id),
                    'shares as shared_by_me' => fn($q) => $q->where('user_id', $user->id)
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

            $posts->getCollection()->transform(function ($post) use ($user) {

                $media = $post->media->map(fn($m) => [
                    'id' => $m->id,
                    'file' => Storage::url($m->file_path),
                    'media_type' => $m->media_type,
                ]);

                $response = [
                    'post_id' => $post->id,
                    'type' => $post->type,
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

                // Type-specific data
                switch ($post->type) {
                    case 'celebration':
                        $response['celebration'] = $post->celebration ? [
                            'celebration_type' => $post->celebration->celebration_type,
                            'title' => $post->celebration->title,
                            'description' => $post->celebration->description,
                        ] : null;
                        break;

                    case 'hiring':
                        $response['hiring'] = $post->hiring ? [
                            'job_title' => $post->hiring->job_title,
                            'company' => $post->hiring->company,
                            'location' => $post->hiring->location,
                            'job_type' => $post->hiring->job_type,
                            'experience' => $post->hiring->experience,
                            'description' => $post->hiring->description,
                        ] : null;
                        break;

                    case 'jobseeker':
                        $response['jobseeker'] = $post->jobseeker ? [
                            'title' => $post->jobseeker->title,
                            'key_skills' => $post->jobseeker->key_skills,
                            'experience' => $post->jobseeker->experience,
                            'work_preference' => $post->jobseeker->work_preference,
                            'about' => $post->jobseeker->about,
                        ] : null;
                        break;

                    case 'find_expert':
                        $response['find_expert'] = $post->findExpert ? [
                            'expertise_needed' => $post->findExpert->expertise_needed,
                            'project_description' => $post->findExpert->project_description,
                            'key_requirements' => $post->findExpert->key_requirements,
                            'duration' => $post->findExpert->duration,
                            'type' => $post->findExpert->type,
                            'budget' => $post->findExpert->budget,
                            'is_urgent' => $post->findExpert->is_urgent,
                        ] : null;
                        break;

                    case 'event':
                        $response['event'] = $post->event ? [
                            'event_name' => $post->event->event_name,
                            'event_type' => $post->event->event_type,
                            'event_date' => $post->event->event_date,
                            'event_time' => $post->event->event_time,
                            'is_online' => $post->event->is_online,
                            'location' => $post->event->location,
                            'description' => $post->event->description,
                            'registration_info' => $post->event->registration_info,
                        ] : null;
                        break;

                    case 'poll':
                        if ($post->poll) {
                            $userVotedOptionIds = PostPollVote::where('poll_id', $post->poll->id)
                                ->where('user_id', $user->id)
                                ->pluck('option_id')
                                ->toArray();

                            $response['poll'] = [
                                'question' => $post->poll->question,
                                'duration_days' => $post->poll->duration_days,
                                'allow_multiple_answers' => $post->poll->allow_multiple_answers,
                                'expires_at' => $post->poll->expires_at,
                                'options' => $post->poll->options->map(fn($option) => [
                                    'id' => $option->id,
                                    'option_text' => $option->option_text,
                                    'votes_count' => $option->votes_count,
                                    'voted_by_me' => in_array($option->id, $userVotedOptionIds),
                                ]),
                            ];
                        } else {
                            $response['poll'] = null; // handle missing poll gracefully
                        }
                        break;
                }

                return $response;
            });

            return $this->successResponse('Timeline retrieved successfully', $posts);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }
}
