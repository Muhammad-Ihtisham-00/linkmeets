<?php

namespace App\Http\Controllers\Api\Social;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Models\UserRelationship;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RelationController extends Controller
{
    use ApiResponse;

    /**
     * Follow a user
     */
    public function follow(Request $request, $userId)
    {
        try {
            $authUser = Auth::user();

            if ($authUser->id == $userId) {
                return $this->errorResponse("You cannot follow yourself.", null, 400);
            }

            // Check if blocked
            $block = UserRelationship::where('user_id', $authUser->id)
                ->where('related_user_id', $userId)
                ->where('type', UserRelationship::BLOCK)
                ->first();

            if ($block) {
                return $this->errorResponse("You cannot follow a user you have blocked.", null, 400);
            }

            UserRelationship::updateOrCreate(
                [
                    'user_id' => $authUser->id,
                    'related_user_id' => $userId,
                    'type' => UserRelationship::FOLLOW
                ]
            );

            return $this->successResponse("Followed successfully.");
        } catch (\Throwable $e) {
            return $this->errorResponse("Something went wrong", $e->getMessage(), 500);
        }
    }

    /**
     * Unfollow a user
     */
    public function unfollow(Request $request, $userId)
    {
        try {
            $authUser = Auth::user();

            $deleted = UserRelationship::where('user_id', $authUser->id)
                ->where('related_user_id', $userId)
                ->where('type', UserRelationship::FOLLOW)
                ->delete();

            if ($deleted) {
                return $this->successResponse("Unfollowed successfully.");
            }

            return $this->errorResponse("You are not following this user.", null, 400);
        } catch (\Throwable $e) {
            return $this->errorResponse("Something went wrong", $e->getMessage(), 500);
        }
    }

    /**
     * Block a user
     */
    public function block(Request $request, $userId)
    {
        try {
            $authUser = Auth::user();

            if ($authUser->id == $userId) {
                return $this->errorResponse("You cannot block yourself.", null, 400);
            }

            // Remove follow if exists
            UserRelationship::where('user_id', $authUser->id)
                ->where('related_user_id', $userId)
                ->where('type', UserRelationship::FOLLOW)
                ->delete();

            UserRelationship::updateOrCreate(
                [
                    'user_id' => $authUser->id,
                    'related_user_id' => $userId,
                    'type' => UserRelationship::BLOCK
                ]
            );

            return $this->successResponse("User blocked successfully.");
        } catch (\Throwable $e) {
            return $this->errorResponse("Something went wrong", $e->getMessage(), 500);
        }
    }

    /**
     * Unblock a user
     */
    public function unblock(Request $request, $userId)
    {
        try {
            $authUser = Auth::user();

            $deleted = UserRelationship::where('user_id', $authUser->id)
                ->where('related_user_id', $userId)
                ->where('type', UserRelationship::BLOCK)
                ->delete();

            if ($deleted) {
                return $this->successResponse("User unblocked successfully.");
            }

            return $this->errorResponse("User is not blocked.", null, 400);
        } catch (\Throwable $e) {
            return $this->errorResponse("Something went wrong", $e->getMessage(), 500);
        }
    }

    /**
     * Get all users the specified user is following
     */
    public function following($userId)
    {
        try {
            $user = User::findOrFail($userId);

            $following = User::join('user_relationships', 'users.id', '=', 'user_relationships.related_user_id')
                ->where('user_relationships.user_id', $user->id)
                ->where('user_relationships.type', UserRelationship::FOLLOW)
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.profile_picture')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'first_name' => $u->first_name,
                        'last_name' => $u->last_name,
                        'username' => $u->username,
                        'profile_picture' => $u->profile_picture ? Storage::url($u->profile_picture) : null,
                    ];
                });

            return $this->successResponse("Following list fetched successfully.", $following);
        } catch (\Throwable $e) {
            return $this->errorResponse("Something went wrong", $e->getMessage(), 500);
        }
    }

    /**
     * Get all followers of the specified user
     */
    public function followers($userId)
    {
        try {
            $user = User::findOrFail($userId);

            $followers = User::join('user_relationships', 'users.id', '=', 'user_relationships.user_id')
                ->where('user_relationships.related_user_id', $user->id)
                ->where('user_relationships.type', UserRelationship::FOLLOW)
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.profile_picture')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'first_name' => $u->first_name,
                        'last_name' => $u->last_name,
                        'username' => $u->username,
                        'profile_picture' => $u->profile_picture ? Storage::url($u->profile_picture) : null,
                    ];
                });

            return $this->successResponse("Followers list fetched successfully.", $followers);
        } catch (\Throwable $e) {
            return $this->errorResponse("Something went wrong", $e->getMessage(), 500);
        }
    }

    /**
     * Get users I am following
     */
    public function myFollowing()
    {
        try {
            $authUser = Auth::user();

            $following = User::join('user_relationships', 'users.id', '=', 'user_relationships.related_user_id')
                ->where('user_relationships.user_id', $authUser->id)
                ->where('user_relationships.type', UserRelationship::FOLLOW)
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.profile_picture')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'first_name' => $u->first_name,
                        'last_name' => $u->last_name,
                        'username' => $u->username,
                        'profile_picture' => $u->profile_picture ? Storage::url($u->profile_picture) : null,
                    ];
                });

            return $this->successResponse("Your following list fetched successfully.", $following);
        } catch (\Throwable $e) {
            return $this->errorResponse("Something went wrong", $e->getMessage(), 500);
        }
    }

    /**
     * Get users following me
     */
    public function myFollowers()
    {
        try {
            $authUser = Auth::user();

            $followers = User::join('user_relationships', 'users.id', '=', 'user_relationships.user_id')
                ->where('user_relationships.related_user_id', $authUser->id)
                ->where('user_relationships.type', UserRelationship::FOLLOW)
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.profile_picture')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'first_name' => $u->first_name,
                        'last_name' => $u->last_name,
                        'username' => $u->username,
                        'profile_picture' => $u->profile_picture ? Storage::url($u->profile_picture) : null,
                    ];
                });

            return $this->successResponse("Your followers list fetched successfully.", $followers);
        } catch (\Throwable $e) {
            return $this->errorResponse("Something went wrong", $e->getMessage(), 500);
        }
    }

    /**
     * Get users I have blocked
     */
    public function myBlocked()
    {
        try {
            $authUser = Auth::user();

            $blocked = User::join('user_relationships', 'users.id', '=', 'user_relationships.related_user_id')
                ->where('user_relationships.user_id', $authUser->id)
                ->where('user_relationships.type', UserRelationship::BLOCK)
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.profile_picture')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'first_name' => $u->first_name,
                        'last_name' => $u->last_name,
                        'username' => $u->username,
                        'profile_picture' => $u->profile_picture ? Storage::url($u->profile_picture) : null,
                    ];
                });

            return $this->successResponse("Your blocked users list fetched successfully.", $blocked);
        } catch (\Throwable $e) {
            return $this->errorResponse("Something went wrong", $e->getMessage(), 500);
        }
    }
}
