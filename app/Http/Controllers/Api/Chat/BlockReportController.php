<?php

namespace App\Http\Controllers\api\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BlockedUser;
use App\Models\ReportedUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BlockReportController extends Controller
{
    use ApiResponse;

    /* ═══════════════════════════════════════════════════════════
       1. BLOCK USER
       POST /api/users/{id}/block
    ═══════════════════════════════════════════════════════════ */
    public function block(int $userId): JsonResponse
    {
        try {
            if ($userId === Auth::id())
                return $this->errorResponse('You cannot block yourself.', null, 422);

            $alreadyBlocked = BlockedUser::where('blocker_id', Auth::id())
                ->where('blocked_id', $userId)
                ->exists();

            if ($alreadyBlocked)
                return $this->errorResponse('User is already blocked.', null, 422);

            BlockedUser::create([
                'blocker_id' => Auth::id(),
                'blocked_id' => $userId,
                'blocked_at' => now(),
            ]);

            return $this->successResponse('User blocked successfully.');

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       2. UNBLOCK USER
       DELETE /api/users/{id}/block
    ═══════════════════════════════════════════════════════════ */
    public function unblock(int $userId): JsonResponse
    {
        try {
            $blocked = BlockedUser::where('blocker_id', Auth::id())
                ->where('blocked_id', $userId)
                ->first();

            if (!$blocked)
                return $this->errorResponse('User is not blocked.', null, 422);

            $blocked->delete();

            return $this->successResponse('User unblocked successfully.');

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       3. MY BLOCKED USERS LIST
       GET /api/users/blocked
    ═══════════════════════════════════════════════════════════ */
    public function blockedList(): JsonResponse
    {
        try {
            $blocked = BlockedUser::where('blocker_id', Auth::id())
                ->with('blocked:id,first_name,last_name,profile_picture')
                ->orderByDesc('blocked_at')
                ->get();

            return $this->successResponse('Blocked users fetched successfully.', $blocked);

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       4. REPORT USER
       POST /api/users/{id}/report
    ═══════════════════════════════════════════════════════════ */
    public function report(Request $request, int $userId): JsonResponse
    {
        try {
            if ($userId === Auth::id())
                return $this->errorResponse('You cannot report yourself.', null, 422);

            $validator = Validator::make($request->all(), [
                'reason' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:1000'],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            ReportedUser::create([
                'reporter_id' => Auth::id(),
                'reported_id' => $userId,
                'reason' => $request->reason,
                'description' => $request->description,
                'status' => 'pending',
                'reported_at' => now(),
            ]);

            return $this->successResponse('User reported successfully. Our team will review it.');

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }
}
