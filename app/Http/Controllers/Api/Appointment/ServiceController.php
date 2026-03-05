<?php

namespace App\Http\Controllers\api\Appointment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Service;
use App\Models\Review;

class ServiceController extends Controller
{
    /* ═══════════════════════════════════════════════════════════
       1. MY SERVICES LIST
       GET /api/services
    ═══════════════════════════════════════════════════════════ */
    public function index(): JsonResponse
    {
        $services = Service::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return $this->success($services, 'Services fetched successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       2. ANY USER'S ACTIVE SERVICES
       GET /api/users/{userId}/services
    ═══════════════════════════════════════════════════════════ */
    public function userServices(int $userId): JsonResponse
    {
        $services = Service::where('user_id', $userId)
            ->active()
            ->orderBy('type')
            ->orderBy('price')
            ->get();

        if ($services->isEmpty())
            return $this->error('No services found for this user.', 404);

        return $this->success($services, 'Services fetched successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       3. SINGLE SERVICE DETAIL
       GET /api/services/{id}
    ═══════════════════════════════════════════════════════════ */
    public function show(int $id): JsonResponse
    {
        $service = Service::with('user:id,first_name,last_name,profile_picture,bio')
            ->find($id);

        if (!$service)
            return $this->error('Service not found.', 404);

        return $this->success($service, 'Service fetched successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       4. CREATE SERVICE
       POST /api/services
    ═══════════════════════════════════════════════════════════ */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', Rule::in(['messaging', 'voice_call', 'video_call', 'event'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        if ($validator->fails())
            return $this->validationError($validator->errors());

        $service = Service::create([
            'user_id' => Auth::id(),
            'type' => $request->type,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'duration_minutes' => $request->duration_minutes,
            'is_active' => true,
        ]);

        return $this->success($service, 'Service created successfully.', 201);
    }

    /* ═══════════════════════════════════════════════════════════
       5. UPDATE SERVICE
       PUT /api/services/{id}
    ═══════════════════════════════════════════════════════════ */
    public function update(Request $request, int $id): JsonResponse
    {
        $service = Service::find($id);

        if (!$service)
            return $this->error('Service not found.', 404);

        if ($service->user_id !== Auth::id())
            return $this->error('You are not authorized to update this service.', 403);

        $validator = Validator::make($request->all(), [
            'type' => ['sometimes', Rule::in(['messaging', 'voice_call', 'video_call', 'event'])],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ]);

        if ($validator->fails())
            return $this->validationError($validator->errors());

        $service->update($request->only([
            'type',
            'title',
            'description',
            'price',
            'duration_minutes',
        ]));

        return $this->success($service->fresh(), 'Service updated successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       6. TOGGLE ACTIVE/INACTIVE
       PUT /api/services/{id}/toggle
    ═══════════════════════════════════════════════════════════ */
    public function toggle(int $id): JsonResponse
    {
        $service = Service::find($id);

        if (!$service)
            return $this->error('Service not found.', 404);

        if ($service->user_id !== Auth::id())
            return $this->error('You are not authorized.', 403);

        $service->update(['is_active' => !$service->is_active]);

        $msg = $service->is_active ? 'Service activated successfully.' : 'Service deactivated successfully.';

        return $this->success($service->fresh(), $msg);
    }

    /* ═══════════════════════════════════════════════════════════
       7. DELETE SERVICE
       DELETE /api/services/{id}
    ═══════════════════════════════════════════════════════════ */
    public function destroy(int $id): JsonResponse
    {
        $service = Service::find($id);

        if (!$service)
            return $this->error('Service not found.', 404);

        if ($service->user_id !== Auth::id())
            return $this->error('You are not authorized to delete this service.', 403);

        $hasActive = $service->appointments()
            ->whereIn('status', ['pending', 'upcoming'])
            ->exists();

        if ($hasActive)
            return $this->error('Cannot delete service with active appointments. Please cancel them first.', 422);

        $service->delete();

        return $this->success(null, 'Service deleted successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       PRIVATE HELPERS
    ═══════════════════════════════════════════════════════════ */
    private function success(mixed $data, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json(['status' => true, 'message' => $message, 'data' => $data], $code);
    }

    private function error(string $message, int $code = 400): JsonResponse
    {
        return response()->json(['status' => false, 'message' => $message, 'data' => null], $code);
    }

    private function validationError(mixed $errors): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);
    }
}
