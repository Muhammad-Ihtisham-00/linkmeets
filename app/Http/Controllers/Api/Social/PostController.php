<?php

namespace App\Http\Controllers\Api\Social;

use App\Models\Post;
use App\Models\PostPoll;
use App\Models\PostEvent;
use App\Models\PostMedia;
use App\Models\PostHiring;
use App\Traits\ApiResponse;
use App\Models\PostPollVote;
use Illuminate\Http\Request;
use App\Models\PostJobSeeker;
use App\Models\PostFindExpert;
use App\Models\PostPollOption;
use App\Models\PostCelebration;
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
                'type' => 'required|in:standard,celebration,hiring,jobseeker,find_expert,event,poll',
                'content' => 'nullable|string|max:5000',
                'visibility' => 'required|in:public,private',

                /*
            |--------------------------------------------------------------------------
            | Media (Standard Posts Only)
            |--------------------------------------------------------------------------
            */
                'media' => 'nullable|array|max:4',
                'media.*.file' => 'required_with:media|file|mimes:jpg,jpeg,png,webp,mp4,pdf,doc,docx|max:51200',
                'media.*.media_type' => 'required_with:media|in:image,video,document',

                /*
            |--------------------------------------------------------------------------
            | Celebration Post Validation
            |--------------------------------------------------------------------------
            */
                'celebration.celebration_type' => 'required_if:type,celebration|string|max:255',
                'celebration.title' => 'required_if:type,celebration|string|max:255',
                'celebration.description' => 'nullable|string|max:2000',

                /*
            |--------------------------------------------------------------------------
            | Hiring Post Validation
            |--------------------------------------------------------------------------
            */
                'hiring.job_title' => 'required_if:type,hiring|string|max:255',
                'hiring.company' => 'required_if:type,hiring|string|max:255',
                'hiring.location' => 'required_if:type,hiring|string|max:255',
                'hiring.job_type' => 'required_if:type,hiring|string|max:255',
                'hiring.experience' => 'required_if:type,hiring|string|max:255',
                'hiring.description' => 'required_if:type,hiring|string|max:5000',

                /*
            |--------------------------------------------------------------------------
            | Job Seeker Post Validation
            |--------------------------------------------------------------------------
            */
                'jobseeker.title' => 'required_if:type,jobseeker|string|max:255',
                'jobseeker.key_skills' => 'required_if:type,jobseeker|string|max:1000',
                'jobseeker.experience' => 'required_if:type,jobseeker|string|max:255',
                'jobseeker.work_preference' => 'required_if:type,jobseeker|string|max:255',
                'jobseeker.about' => 'required_if:type,jobseeker|string|max:2000',

                /*
            |--------------------------------------------------------------------------
            | Find Expert Post Validation
            |--------------------------------------------------------------------------
            */
                'find_expert.expertise_needed' => 'required_if:type,find_expert|string|max:255',
                'find_expert.project_description' => 'required_if:type,find_expert|string|max:2000',
                'find_expert.key_requirements' => 'required_if:type,find_expert|string|max:2000',
                'find_expert.duration' => 'required_if:type,find_expert|string|max:255',
                'find_expert.type' => 'required_if:type,find_expert|string|max:255',
                'find_expert.budget' => 'nullable|string|max:255',
                'find_expert.is_urgent' => 'nullable|boolean',

                /*
            |--------------------------------------------------------------------------
            | Event Post Validation
            |--------------------------------------------------------------------------
            */
                'event.event_name' => 'required_if:type,event|string|max:255',
                'event.event_type' => 'required_if:type,event|string|max:255',
                'event.event_date' => 'required_if:type,event|date',
                'event.event_time' => 'required_if:type,event|date_format:H:i',
                'event.is_online' => 'nullable|boolean',
                'event.location' => 'nullable|string|max:255',
                'event.description' => 'required_if:type,event|string|max:2000',
                'event.registration_info' => 'nullable|string|max:255',

                /*
            |--------------------------------------------------------------------------
            | Poll Post Validation
            |--------------------------------------------------------------------------
            */
                'poll.question' => 'required_if:type,poll|string|max:255',
                'poll.duration_days' => 'required_if:type,poll|integer|min:1|max:30',
                'poll.allow_multiple_answers' => 'nullable|boolean',
                'poll.options' => 'required_if:type,poll|array|min:2|max:10',
                'poll.options.*' => 'required|string|max:255',
            ]);

            $user = $request->user();

            // -----------------------------
            // Create Base Post
            // -----------------------------
            $post = Post::create([
                'user_id' => $user->id,
                'content' => $validated['content'] ?? null,
                'visibility' => $validated['visibility'],
                'type' => $validated['type'],
            ]);

            // -----------------------------
            // Initialize type-specific data
            // -----------------------------
            $celebrationData = null;
            $hiringData = null;
            $jobseekerData = null;
            $findExpertData = null;
            $eventData = null;
            $pollData = null;
            $uploadedMedia = [];

            // -----------------------------
            // Celebration
            // -----------------------------
            if ($validated['type'] === 'celebration') {

                $celebration = PostCelebration::create([
                    'post_id' => $post->id,
                    'celebration_type' => $validated['celebration']['celebration_type'],
                    'title' => $validated['celebration']['title'],
                    'description' => $validated['celebration']['description'] ?? null,
                ]);

                $celebrationData = [
                    'celebration_type' => $celebration->celebration_type,
                    'title' => $celebration->title,
                    'description' => $celebration->description,
                ];
            }

            // -----------------------------
            // Hiring
            // -----------------------------
            if ($validated['type'] === 'hiring') {

                $hiring = PostHiring::create([
                    'post_id' => $post->id,
                    'job_title' => $validated['hiring']['job_title'],
                    'company' => $validated['hiring']['company'],
                    'location' => $validated['hiring']['location'],
                    'job_type' => $validated['hiring']['job_type'],
                    'experience' => $validated['hiring']['experience'],
                    'description' => $validated['hiring']['description'],
                ]);

                $hiringData = [
                    'job_title' => $hiring->job_title,
                    'company' => $hiring->company,
                    'location' => $hiring->location,
                    'job_type' => $hiring->job_type,
                    'experience' => $hiring->experience,
                    'description' => $hiring->description,
                ];
            }

            // -----------------------------
            // Job Seeker
            // -----------------------------
            if ($validated['type'] === 'jobseeker') {

                $jobseeker = PostJobSeeker::create([
                    'post_id' => $post->id,
                    'title' => $validated['jobseeker']['title'],
                    'key_skills' => $validated['jobseeker']['key_skills'],
                    'experience' => $validated['jobseeker']['experience'],
                    'work_preference' => $validated['jobseeker']['work_preference'],
                    'about' => $validated['jobseeker']['about'],
                ]);

                $jobseekerData = [
                    'title' => $jobseeker->title,
                    'key_skills' => $jobseeker->key_skills,
                    'experience' => $jobseeker->experience,
                    'work_preference' => $jobseeker->work_preference,
                    'about' => $jobseeker->about,
                ];
            }

            // -----------------------------
            // Find Expert
            // -----------------------------
            if ($validated['type'] === 'find_expert') {

                $findExpert = PostFindExpert::create([
                    'post_id' => $post->id,
                    'expertise_needed' => $validated['find_expert']['expertise_needed'],
                    'project_description' => $validated['find_expert']['project_description'],
                    'key_requirements' => $validated['find_expert']['key_requirements'],
                    'duration' => $validated['find_expert']['duration'],
                    'type' => $validated['find_expert']['type'],
                    'budget' => $validated['find_expert']['budget'] ?? null,
                    'is_urgent' => $validated['find_expert']['is_urgent'] ?? false,
                ]);

                $findExpertData = [
                    'expertise_needed' => $findExpert->expertise_needed,
                    'project_description' => $findExpert->project_description,
                    'key_requirements' => $findExpert->key_requirements,
                    'duration' => $findExpert->duration,
                    'type' => $findExpert->type,
                    'budget' => $findExpert->budget,
                    'is_urgent' => $findExpert->is_urgent,
                ];
            }

            // -----------------------------
            // Event
            // -----------------------------
            if ($validated['type'] === 'event') {

                $event = PostEvent::create([
                    'post_id' => $post->id,
                    'event_name' => $validated['event']['event_name'],
                    'event_type' => $validated['event']['event_type'],
                    'event_date' => $validated['event']['event_date'],
                    'event_time' => $validated['event']['event_time'],
                    'is_online' => $validated['event']['is_online'] ?? false,
                    'location' => $validated['event']['location'] ?? null,
                    'description' => $validated['event']['description'],
                    'registration_info' => $validated['event']['registration_info'] ?? null,
                ]);

                $eventData = [
                    'event_name' => $event->event_name,
                    'event_type' => $event->event_type,
                    'event_date' => $event->event_date,
                    'event_time' => $event->event_time,
                    'is_online' => $event->is_online,
                    'location' => $event->location,
                    'description' => $event->description,
                    'registration_info' => $event->registration_info,
                ];
            }

            // -----------------------------
            // Poll
            // -----------------------------
            if ($validated['type'] === 'poll') {

                $expiresAt = now()->addDays($validated['poll']['duration_days']);

                $poll = PostPoll::create([
                    'post_id' => $post->id,
                    'question' => $validated['poll']['question'],
                    'duration_days' => $validated['poll']['duration_days'],
                    'allow_multiple_answers' => $validated['poll']['allow_multiple_answers'] ?? false,
                    'expires_at' => $expiresAt,
                ]);

                $options = [];

                foreach ($validated['poll']['options'] as $option) {

                    $pollOption = PostPollOption::create([
                        'poll_id' => $poll->id,
                        'option_text' => $option,
                    ]);

                    $options[] = [
                        'id' => $pollOption->id,
                        'option_text' => $pollOption->option_text,
                        'votes_count' => 0
                    ];
                }

                $pollData = [
                    'id' => $poll->id,
                    'question' => $poll->question,
                    'duration_days' => $poll->duration_days,
                    'allow_multiple_answers' => $poll->allow_multiple_answers,
                    'expires_at' => $poll->expires_at,
                    'options' => $options
                ];
            }

            // -----------------------------
            // Media Upload (Standard Only)
            // -----------------------------
            if ($validated['type'] === 'standard' && !empty($validated['media'])) {

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

            // -----------------------------
            // Response
            // -----------------------------
            return $this->successResponse(
                'Post created successfully',
                [
                    'post_id' => $post->id,
                    'type' => $post->type,
                    'content' => $post->content,
                    'visibility' => $post->visibility,
                    'celebration' => $celebrationData,
                    'hiring' => $hiringData,
                    'jobseeker' => $jobseekerData,
                    'find_expert' => $findExpertData,
                    'event' => $eventData,
                    'poll' => $pollData,
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

            // Load post with type-specific relations
            $post = Post::with([
                'user',
                'media',
                'celebration',
                'hiring',
                'jobseeker',
                'findExpert',
                'event',
                'poll.options'
            ])
                ->withCount(['likes', 'comments', 'shares'])
                ->withExists([
                    'likes as liked_by_me' => fn($q) => $q->where('user_id', $user->id),
                    'shares as shared_by_me' => fn($q) => $q->where('user_id', $user->id)
                ])
                ->findOrFail($postId);

            // Check private visibility
            if ($post->visibility === 'private' && $post->user_id !== $user->id) {
                return $this->errorResponse('This post is private', null, 403);
            }

            // Increment views if not owner
            if ($post->user_id !== $user->id) {
                $post->increment('views_count');
            }

            // Media (only standard posts)
            $media = [];
            if ($post->type === 'standard') {
                $media = $post->media->map(fn($m) => [
                    'id' => $m->id,
                    'file' => Storage::url($m->file_path),
                    'media_type' => $m->media_type
                ]);
            }

            // Type-specific data
            $celebration = $post->celebration ? [
                'celebration_type' => $post->celebration->celebration_type,
                'title' => $post->celebration->title,
                'description' => $post->celebration->description
            ] : null;

            $hiring = $post->hiring ? [
                'job_title' => $post->hiring->job_title,
                'company' => $post->hiring->company,
                'location' => $post->hiring->location,
                'job_type' => $post->hiring->job_type,
                'experience' => $post->hiring->experience,
                'description' => $post->hiring->description
            ] : null;

            $jobseeker = $post->jobseeker ? [
                'title' => $post->jobseeker->title,
                'key_skills' => $post->jobseeker->key_skills,
                'experience' => $post->jobseeker->experience,
                'work_preference' => $post->jobseeker->work_preference,
                'about' => $post->jobseeker->about
            ] : null;

            $findExpert = $post->findExpert ? [
                'expertise_needed' => $post->findExpert->expertise_needed,
                'project_description' => $post->findExpert->project_description,
                'key_requirements' => $post->findExpert->key_requirements,
                'duration' => $post->findExpert->duration,
                'type' => $post->findExpert->type,
                'budget' => $post->findExpert->budget,
                'is_urgent' => $post->findExpert->is_urgent
            ] : null;

            $event = $post->event ? [
                'event_name' => $post->event->event_name,
                'event_type' => $post->event->event_type,
                'event_date' => $post->event->event_date,
                'event_time' => $post->event->event_time,
                'is_online' => $post->event->is_online,
                'location' => $post->event->location,
                'description' => $post->event->description,
                'registration_info' => $post->event->registration_info
            ] : null;

            $poll = null;
            if ($post->poll) {
                // Get option IDs user voted for
                $userVotedOptionIds = PostPollVote::where('poll_id', $post->poll->id)
                    ->where('user_id', $user->id)
                    ->pluck('option_id')
                    ->toArray();

                $poll = [
                    'id' => $post->poll->id,
                    'question' => $post->poll->question,
                    'duration_days' => $post->poll->duration_days,
                    'allow_multiple_answers' => $post->poll->allow_multiple_answers,
                    'expires_at' => $post->poll->expires_at,
                    'options' => $post->poll->options->map(fn($option) => [
                        'id' => $option->id,
                        'option_text' => $option->option_text,
                        'votes_count' => $option->votes_count,
                        'voted_by_me' => in_array($option->id, $userVotedOptionIds)
                    ])
                ];
            }

            // Final response
            $data = [
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
                'celebration' => $celebration,
                'hiring' => $hiring,
                'jobseeker' => $jobseeker,
                'find_expert' => $findExpert,
                'event' => $event,
                'poll' => $poll,
                'created_at' => $post->created_at,
            ];

            return $this->successResponse('Post retrieved successfully', $data);
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
