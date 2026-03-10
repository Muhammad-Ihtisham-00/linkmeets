<?php

namespace App\Http\Controllers\api\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\ConversationParticipant;
use App\Traits\ApiResponse;

class MessageController extends Controller
{
    use ApiResponse;

    /* ═══════════════════════════════════════════════════════════
       1. GET MESSAGES
       GET /api/conversations/{id}/messages
    ═══════════════════════════════════════════════════════════ */
    public function index(int $conversationId): JsonResponse
    {
        try {
            $conversation = Conversation::find($conversationId);

            if (!$conversation)
                return $this->errorResponse('Conversation not found.', null, 404);

            if (!$conversation->hasParticipant(Auth::id()))
                return $this->errorResponse('You are not part of this conversation.', null, 403);

            $messages = Message::where('conversation_id', $conversationId)
                ->where('is_deleted', false)
                ->with([
                    'sender:id,first_name,last_name,profile_picture',
                    'reads.user:id,first_name,last_name',
                ])
                ->orderByDesc('created_at')
                ->paginate(20);

            return $this->successResponse('Messages fetched successfully.', $messages);

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       2. SEND MESSAGE
       POST /api/conversations/{id}/messages
    ═══════════════════════════════════════════════════════════ */
    public function store(Request $request, int $conversationId): JsonResponse
    {
        try {
            $conversation = Conversation::find($conversationId);

            if (!$conversation)
                return $this->errorResponse('Conversation not found.', null, 404);

            if (!$conversation->hasParticipant(Auth::id()))
                return $this->errorResponse('You are not part of this conversation.', null, 403);

            // Block check for private conversations
            if ($conversation->type === 'private') {
                $otherParticipant = $conversation->participantRecords()
                    ->where('user_id', '!=', Auth::id())
                    ->first();

                if ($otherParticipant) {
                    if (Auth::user()->hasBlocked($otherParticipant->user_id))
                        return $this->errorResponse('You have blocked this user.', null, 403);

                    if (Auth::user()->isBlockedBy($otherParticipant->user_id))
                        return $this->errorResponse('You cannot send messages to this user.', null, 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'type' => ['required', Rule::in(['text', 'image', 'document', 'audio', 'location', 'live_location'])],
                'body' => ['required_if:type,text', 'nullable', 'string', 'max:5000'],
                'file' => ['required_if:type,image,document,audio', 'nullable', 'file', 'max:20480'],
                'latitude' => ['required_if:type,location,live_location', 'nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['required_if:type,location,live_location', 'nullable', 'numeric', 'between:-180,180'],
                'location_name' => ['nullable', 'string', 'max:255'],
                'live_location_expires_at' => ['required_if:type,live_location', 'nullable', 'date', 'after:now'],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            DB::beginTransaction();

            $messageData = [
                'conversation_id' => $conversationId,
                'sender_id' => Auth::id(),
                'type' => $request->type,
                'body' => $request->body,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'location_name' => $request->location_name,
            ];

            // File upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $folder = match ($request->type) {
                    'image' => 'chat/images',
                    'document' => 'chat/documents',
                    'audio' => 'chat/audio',
                    default => 'chat/files',
                };

                $path = $file->store($folder, 'public');

                $messageData['file_path'] = $path;
                $messageData['file_name'] = $file->getClientOriginalName();
                $messageData['file_size'] = $file->getSize();
                $messageData['mime_type'] = $file->getMimeType();
            }

            // Live location
            if ($request->type === 'live_location') {
                $messageData['is_live_location'] = true;
                $messageData['live_location_expires_at'] = $request->live_location_expires_at;
            }

            $message = Message::create($messageData);

            // Update conversation last message
            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => now(),
            ]);

            // Auto mark as read for sender
            MessageRead::create([
                'message_id' => $message->id,
                'user_id' => Auth::id(),
                'read_at' => now(),
            ]);

            DB::commit();

            return $this->successResponse(
                'Message sent successfully.',
                $message->load(['sender:id,first_name,last_name,profile_picture', 'reads']),
                201
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       3. MARK MESSAGES AS READ
       PUT /api/conversations/{id}/read
    ═══════════════════════════════════════════════════════════ */
    public function markAsRead(int $conversationId): JsonResponse
    {
        try {
            $conversation = Conversation::find($conversationId);

            if (!$conversation)
                return $this->errorResponse('Conversation not found.', null, 404);

            if (!$conversation->hasParticipant(Auth::id()))
                return $this->errorResponse('You are not part of this conversation.', null, 403);

            $unreadMessages = Message::where('conversation_id', $conversationId)
                ->where('sender_id', '!=', Auth::id())
                ->whereDoesntHave('reads', fn($q) => $q->where('user_id', Auth::id()))
                ->pluck('id');

            $reads = $unreadMessages->map(fn($id) => [
                'message_id' => $id,
                'user_id' => Auth::id(),
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            if (!empty($reads))
                MessageRead::insert($reads);

            ConversationParticipant::where('conversation_id', $conversationId)
                ->where('user_id', Auth::id())
                ->update(['last_read_at' => now()]);

            return $this->successResponse('Messages marked as read.', [
                'marked_count' => count($reads),
            ]);

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       4. DELETE MESSAGE
       DELETE /api/messages/{id}
    ═══════════════════════════════════════════════════════════ */
    public function destroy(int $id): JsonResponse
    {
        try {
            $message = Message::find($id);

            if (!$message)
                return $this->errorResponse('Message not found.', null, 404);

            if ($message->sender_id !== Auth::id())
                return $this->errorResponse('You can only delete your own messages.', null, 403);

            if ($message->is_deleted)
                return $this->errorResponse('Message already deleted.', null, 422);

            // File delete from storage
            if ($message->file_path && Storage::disk('public')->exists($message->file_path))
                Storage::disk('public')->delete($message->file_path);

            $message->update([
                'is_deleted' => true,
                'deleted_at' => now(),
                'body' => null,
                'file_path' => null,
            ]);

            return $this->successResponse('Message deleted successfully.');

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       5. UPDATE LIVE LOCATION
       PUT /api/messages/{id}/live-location
    ═══════════════════════════════════════════════════════════ */
    public function updateLiveLocation(Request $request, int $id): JsonResponse
    {
        try {
            $message = Message::find($id);

            if (!$message)
                return $this->errorResponse('Message not found.', null, 404);

            if ($message->sender_id !== Auth::id())
                return $this->errorResponse('Unauthorized.', null, 403);

            if (!$message->is_live_location)
                return $this->errorResponse('This is not a live location message.', null, 422);

            if ($message->live_location_stopped)
                return $this->errorResponse('Live location sharing has been stopped.', null, 422);

            if (now()->gt($message->live_location_expires_at))
                return $this->errorResponse('Live location has expired.', null, 422);

            $validator = Validator::make($request->all(), [
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            $message->update([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            return $this->successResponse('Live location updated.', $message->fresh());

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       6. STOP LIVE LOCATION
       DELETE /api/messages/{id}/live-location
    ═══════════════════════════════════════════════════════════ */
    public function stopLiveLocation(int $id): JsonResponse
    {
        try {
            $message = Message::find($id);

            if (!$message)
                return $this->errorResponse('Message not found.', null, 404);

            if ($message->sender_id !== Auth::id())
                return $this->errorResponse('Unauthorized.', null, 403);

            if (!$message->is_live_location)
                return $this->errorResponse('This is not a live location message.', null, 422);

            if ($message->live_location_stopped)
                return $this->errorResponse('Live location is already stopped.', null, 422);

            $message->update(['live_location_stopped' => true]);

            return $this->successResponse('Live location sharing stopped.');

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }
}
