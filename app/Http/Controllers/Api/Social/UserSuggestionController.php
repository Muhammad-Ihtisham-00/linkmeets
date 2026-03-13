<?php

namespace App\Http\Controllers\Api\Social;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserRelationship;
use App\Traits\ApiResponse;

class UserSuggestionController extends Controller
{
    use ApiResponse;

    /**
     * Get suggested users (mutual connections + shared interests)
     */
    public function suggestedUsers(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;

        // 1️⃣ Users already followed or blocked (exclude them)
        $excludedUserIds = UserRelationship::where('user_id', $userId)
            ->pluck('related_user_id')
            ->toArray();

        $excludedUserIds[] = $userId; // exclude self

        // 2️⃣ Friends-of-friends (mutual connections)
        $mutuals = UserRelationship::whereIn('user_id', function ($q) use ($userId) {
            $q->select('related_user_id')
                ->from('user_relationships')
                ->where('user_id', $userId)
                ->where('type', UserRelationship::FOLLOW);
        })
            ->where('type', UserRelationship::FOLLOW)
            ->whereNotIn('related_user_id', $excludedUserIds)
            ->pluck('related_user_id')
            ->toArray();

        // 3️⃣ Users with shared interests
        $interestIds = $user->interests()->pluck('interest_id')->toArray();
        $interestUsers = [];

        if (!empty($interestIds)) {
            $interestUsers = User::whereHas('interests', function ($q) use ($interestIds) {
                $q->whereIn('interest_id', $interestIds);
            })
                ->whereNotIn('id', $excludedUserIds)
                ->pluck('id')
                ->toArray();
        }

        // 4️⃣ Combine suggestions and remove duplicates
        $suggestedIds = array_unique(array_merge($mutuals, $interestUsers));

        // 5️⃣ Limit to 20
        $suggestedUsers = User::whereIn('id', $suggestedIds)
            ->limit(20)
            ->get();

        // 6️⃣ Return response
        return $this->successResponse('Suggested users fetched successfully', $suggestedUsers);
    }
}
