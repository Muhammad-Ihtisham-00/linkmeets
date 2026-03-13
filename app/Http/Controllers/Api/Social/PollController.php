<?php

namespace App\Http\Controllers\Api\Social;

use App\Models\PostPoll;
use App\Models\PostPollVote;
use App\Models\PostPollOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;

class PollController extends Controller
{
    use ApiResponse;

    /*
    |--------------------------------------------------------------------------
    | Vote in Poll
    |--------------------------------------------------------------------------
    */
    public function vote(Request $request, $pollId)
    {
        $user = $request->user();

        $request->validate([
            'option_ids' => 'required|array|min:1',
            'option_ids.*' => 'exists:post_poll_options,id'
        ]);

        $poll = PostPoll::with('options')->findOrFail($pollId);

        // -----------------------------
        // Check if poll expired
        // -----------------------------
        if ($poll->expires_at && now()->greaterThan($poll->expires_at)) {
            return $this->errorResponse('Poll has expired', null, 403);
        }

        // -----------------------------
        // Check multiple answers
        // -----------------------------
        if (!$poll->allow_multiple_answers && count($request->option_ids) > 1) {
            return $this->errorResponse('Multiple answers are not allowed in this poll', null, 422);
        }

        // -----------------------------
        // Check if user already voted
        // -----------------------------
        $existingVotes = PostPollVote::where('poll_id', $poll->id)
            ->where('user_id', $user->id)
            ->pluck('option_id');

        if ($existingVotes->count() > 0 && !$poll->allow_multiple_answers) {
            return $this->errorResponse('You have already voted in this poll', null, 409);
        }

        // -----------------------------
        // Save Votes
        // -----------------------------
        DB::beginTransaction();

        try {
            foreach ($request->option_ids as $optionId) {

                $option = PostPollOption::where('id', $optionId)
                    ->where('poll_id', $poll->id)
                    ->first();

                if (!$option) {
                    DB::rollBack();
                    return $this->errorResponse('Invalid poll option', null, 422);
                }

                $vote = PostPollVote::firstOrCreate([
                    'poll_id' => $poll->id,
                    'option_id' => $optionId,
                    'user_id' => $user->id
                ]);

                // Increment votes_count if newly created
                if ($vote->wasRecentlyCreated) {
                    $option->increment('votes_count');
                }
            }

            DB::commit();

            return $this->successResponse('Vote recorded successfully');
        } catch (\Throwable $e) {

            DB::rollBack();

            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Poll Results
    |--------------------------------------------------------------------------
    */
    public function results(Request $request, $pollId)
    {
        $user = $request->user();

        $poll = PostPoll::with('options')->findOrFail($pollId);

        // Get user's votes for this poll
        $userVotedOptionIds = PostPollVote::where('poll_id', $poll->id)
            ->where('user_id', $user->id)
            ->pluck('option_id')
            ->toArray();

        $results = $poll->options->map(function ($option) use ($userVotedOptionIds) {
            return [
                'id' => $option->id,
                'option_text' => $option->option_text,
                'votes_count' => $option->votes_count, // total votes
                'voted_by_me' => in_array($option->id, $userVotedOptionIds), // user's vote
            ];
        });

        return $this->successResponse('Poll results retrieved successfully', [
            'poll_id' => $poll->id,
            'question' => $poll->question,
            'allow_multiple_answers' => $poll->allow_multiple_answers,
            'expires_at' => $poll->expires_at,
            'results' => $results,
        ]);
    }
}
