<?php

namespace App\Http\Controllers\api\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\BlockedUser;
use App\Models\ReportedUser;
use App\Models\User;
use App\Traits\ApiResponse;

class ConversationController extends Controller
{
    use ApiResponse;

    /* ═══════════════════════════════════════════════════════════
       1. MY CONVERSATIONS LIST
       GET /api/conversations
    ═══════════════════════════════════════════════════════════ */
    public function index(): JsonResponse
    {
        try {
            $conversations = Conversation::whereHas('participantRecords', function ($q) {
                $q->where('user_id', Auth::id())->whereNull('left_at');
            })
                ->with([
                    'participants:id,first_name,last_name,profile_picture',
                    'lastMessage.sender:id,first_name,last_name',
                    'creator:id,first_name,last_name,profile_picture',
                ])
                ->orderByDesc('last_message_at')
                ->get()
                ->map(function ($conversation) {
                    $conversation->unread_count = $conversation->unreadCount(Auth::id());
                    return $conversation;
                });

            return $this->successResponse('Conversations fetched successfully.', $conversations);

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       2. START PRIVATE CONVERSATION
       POST /api/conversations/private
    ═══════════════════════════════════════════════════════════ */
    public function startPrivate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => ['required', 'exists:users,id', 'different:' . Auth::id()],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            // Block check
            if (Auth::user()->hasBlocked($request->user_id))
                return $this->errorResponse('You have blocked this user.', null, 403);

            if (Auth::user()->isBlockedBy($request->user_id))
                return $this->errorResponse('You cannot message this user.', null, 403);

            // Already existing private conversation check
            $existing = Conversation::where('type', 'private')
                ->whereHas('participantRecords', fn($q) => $q->where('user_id', Auth::id()))
                ->whereHas('participantRecords', fn($q) => $q->where('user_id', $request->user_id))
                ->first();

            if ($existing)
                return $this->successResponse('Conversation already exists.', $existing->load([
                    'participants:id,first_name,last_name,profile_picture',
                    'lastMessage',
                ]));

            DB::beginTransaction();

            $conversation = Conversation::create([
                'type' => 'private',
                'created_by' => Auth::id(),
            ]);

            ConversationParticipant::insert([
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => Auth::id(),
                    'role' => 'member',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $request->user_id,
                    'role' => 'member',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::commit();

            return $this->successResponse(
                'Conversation started successfully.',
                $conversation->load(['participants:id,first_name,last_name,profile_picture']),
                201
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       3. CREATE GROUP
       POST /api/conversations/group
    ═══════════════════════════════════════════════════════════ */
    public function createGroup(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'image' => ['nullable', 'string'],
                'member_ids' => ['required', 'array', 'min:1'],
                'member_ids.*' => ['exists:users,id', 'different:' . Auth::id()],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            DB::beginTransaction();

            $conversation = Conversation::create([
                'type' => 'group',
                'name' => $request->name,
                'image' => $request->image,
                'created_by' => Auth::id(),
            ]);

            // Creator as admin + members
            $participants = [
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => Auth::id(),
                    'role' => 'admin',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ];

            foreach ($request->member_ids as $memberId) {
                $participants[] = [
                    'conversation_id' => $conversation->id,
                    'user_id' => $memberId,
                    'role' => 'member',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            ConversationParticipant::insert($participants);

            DB::commit();

            return $this->successResponse(
                'Group created successfully.',
                $conversation->load([
                    'participants:id,first_name,last_name,profile_picture',
                    'creator:id,first_name,last_name',
                ]),
                201
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       4. CONVERSATION DETAIL
       GET /api/conversations/{id}
    ═══════════════════════════════════════════════════════════ */
    public function show(int $id): JsonResponse
    {
        try {
            $conversation = Conversation::with([
                'participants:id,first_name,last_name,profile_picture',
                'participantRecords.user:id,first_name,last_name,profile_picture',
                'creator:id,first_name,last_name',
                'lastMessage.sender:id,first_name,last_name',
            ])->find($id);

            if (!$conversation)
                return $this->errorResponse('Conversation not found.', null, 404);

            if (!$conversation->hasParticipant(Auth::id()))
                return $this->errorResponse('You are not part of this conversation.', null, 403);

            $conversation->unread_count = $conversation->unreadCount(Auth::id());

            return $this->successResponse('Conversation fetched successfully.', $conversation);

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       5. UPDATE GROUP
       PUT /api/conversations/{id}/group
    ═══════════════════════════════════════════════════════════ */
    public function updateGroup(Request $request, int $id): JsonResponse
    {
        try {
            $conversation = Conversation::find($id);

            if (!$conversation)
                return $this->errorResponse('Conversation not found.', null, 404);

            if ($conversation->type !== 'group')
                return $this->errorResponse('This is not a group conversation.', null, 422);

            $participant = ConversationParticipant::where('conversation_id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$participant || !$participant->isAdmin())
                return $this->errorResponse('Only group admin can update group details.', null, 403);

            $validator = Validator::make($request->all(), [
                'name' => ['sometimes', 'string', 'max:255'],
                'image' => ['nullable', 'string'],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            $conversation->update($request->only(['name', 'image']));

            return $this->successResponse(
                'Group updated successfully.',
                $conversation->fresh()->load(['participants:id,first_name,last_name,profile_picture'])
            );

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       6. ADD PARTICIPANTS TO GROUP
       POST /api/conversations/{id}/participants
    ═══════════════════════════════════════════════════════════ */
    public function addParticipants(Request $request, int $id): JsonResponse
    {
        try {
            $conversation = Conversation::find($id);

            if (!$conversation)
                return $this->errorResponse('Conversation not found.', null, 404);

            if ($conversation->type !== 'group')
                return $this->errorResponse('Cannot add participants to a private conversation.', null, 422);

            $participant = ConversationParticipant::where('conversation_id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$participant || !$participant->isAdmin())
                return $this->errorResponse('Only group admin can add participants.', null, 403);

            $validator = Validator::make($request->all(), [
                'user_ids' => ['required', 'array', 'min:1'],
                'user_ids.*' => ['exists:users,id'],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            DB::beginTransaction();

            $added = [];
            foreach ($request->user_ids as $userId) {
                $exists = ConversationParticipant::where('conversation_id', $id)
                    ->where('user_id', $userId)
                    ->exists();

                if (!$exists) {
                    ConversationParticipant::create([
                        'conversation_id' => $id,
                        'user_id' => $userId,
                        'role' => 'member',
                        'joined_at' => now(),
                    ]);
                    $added[] = $userId;
                }
            }

            DB::commit();

            return $this->successResponse(
                count($added) . ' participant(s) added successfully.',
                $conversation->load('participants:id,first_name,last_name,profile_picture')
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       7. LEAVE GROUP
       DELETE /api/conversations/{id}/leave
    ═══════════════════════════════════════════════════════════ */
    public function leave(int $id): JsonResponse
    {
        try {
            $conversation = Conversation::find($id);

            if (!$conversation)
                return $this->errorResponse('Conversation not found.', null, 404);

            if ($conversation->type !== 'group')
                return $this->errorResponse('You cannot leave a private conversation.', null, 422);

            $participant = ConversationParticipant::where('conversation_id', $id)
                ->where('user_id', Auth::id())
                ->whereNull('left_at')
                ->first();

            if (!$participant)
                return $this->errorResponse('You are not part of this group.', null, 403);

            $participant->update(['left_at' => now()]);

            return $this->successResponse('You have left the group successfully.');

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }
}
