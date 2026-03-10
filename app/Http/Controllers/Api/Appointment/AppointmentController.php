<?php

namespace App\Http\Controllers\Api\Appointment;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\Service;
use App\Models\User;

class AppointmentController extends Controller
{
    /* ═══════════════════════════════════════════════════════════
       1. BOOK APPOINTMENT
       POST /api/appointments
    ═══════════════════════════════════════════════════════════ */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider_id' => ['required', 'exists:users,id'],
            'service_id' => ['required', 'exists:services,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            // Event only
            'event_location_name' => ['nullable', 'string', 'max:255'],
            'event_address' => ['nullable', 'string'],
            'event_distance_km' => ['nullable', 'numeric', 'min:0'],
            'event_image' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails())
            return $this->validationError($validator->errors());

        // Apne aap ko book nahi kar sakta
        if ($request->provider_id == Auth::id())
            return $this->error('You cannot book an appointment with yourself.', 422);

        // Service provider ki hi honi chahiye
        $service = Service::find($request->service_id);
        if (!$service || $service->user_id != $request->provider_id)
            return $this->error('This service does not belong to the selected provider.', 422);

        // Service active honi chahiye
        if (!$service->is_active)
            return $this->error('This service is currently unavailable.', 422);

        // Event type service k liye location required
        if ($service->type === 'event' && !$request->event_location_name)
            return $this->error('Event location is required for event appointments.', 422);

        DB::beginTransaction();

        try {
            // ✅ Correct overlap + lockForUpdate
            $conflict = Appointment::where('provider_id', $request->provider_id)
                ->where('appointment_date', $request->appointment_date)
                ->whereIn('status', ['pending', 'upcoming'])
                ->where(function ($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                        ->where('end_time', '>', $request->start_time);
                })
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                DB::rollBack();
                return $this->error('This time slot is already booked. Please choose another time.', 409);
            }

            $appointment = Appointment::create([
                'client_id' => Auth::id(),
                'provider_id' => $request->provider_id,
                'service_id' => $request->service_id,
                'full_name' => $request->full_name,
                'reason' => $request->reason,
                'appointment_date' => $request->appointment_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'event_location_name' => $request->event_location_name,
                'event_address' => $request->event_address,
                'event_distance_km' => $request->event_distance_km,
                'event_image' => $request->event_image,
                'notes' => $request->notes,
                'status' => 'pending',
            ]);

            DB::commit();

            return $this->success(
                $appointment->load(['client', 'provider', 'service']),
                'Appointment booked successfully.',
                201
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->serverError($e);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       2. MY APPOINTMENTS LIST
       GET /api/appointments?status=upcoming|completed|cancelled
    ═══════════════════════════════════════════════════════════ */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['pending', 'upcoming', 'completed', 'cancelled', 'rescheduled'])],
        ]);

        if ($validator->fails())
            return $this->validationError($validator->errors());

        $appointments = Appointment::where(function ($q) {
            $q->where('client_id', Auth::id())
                ->orWhere('provider_id', Auth::id());
        })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->with([
                'client:id,first_name,last_name,profile_picture',
                'provider:id,first_name,last_name,profile_picture,bio',
                'service:id,type,title,price,duration_minutes',
                'review:id,appointment_id,rating',
            ])
            ->orderByDesc('appointment_date')
            ->orderByDesc('start_time')
            ->paginate(10);

        return $this->success($appointments, 'Appointments fetched successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       3. SINGLE APPOINTMENT DETAIL
       GET /api/appointments/{id}
    ═══════════════════════════════════════════════════════════ */
    public function show(int $id): JsonResponse
    {
        $appointment = Appointment::with([
            'client:id,first_name,last_name,profile_picture,phone',
            'provider:id,first_name,last_name,profile_picture,bio,about',
            'service',
            'cancelledBy:id,first_name,last_name',
            'rescheduledFrom:id,appointment_date,start_time,end_time',
            'review',
        ])->find($id);

        if (!$appointment)
            return $this->error('Appointment not found.', 404);

        if (!in_array(Auth::id(), [$appointment->client_id, $appointment->provider_id]))
            return $this->error('You are not authorized to view this appointment.', 403);

        return $this->success($appointment, 'Appointment details fetched successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       4. CANCEL APPOINTMENT
       PUT /api/appointments/{id}/cancel
    ═══════════════════════════════════════════════════════════ */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails())
            return $this->validationError($validator->errors());

        $appointment = Appointment::find($id);

        if (!$appointment)
            return $this->error('Appointment not found.', 404);

        if (!in_array(Auth::id(), [$appointment->client_id, $appointment->provider_id]))
            return $this->error('You are not authorized to cancel this appointment.', 403);

        if (!in_array($appointment->status, ['pending', 'upcoming']))
            return $this->error('Only pending or upcoming appointments can be cancelled.', 422);

        if ($appointment->appointment_date < now()->toDateString())
            return $this->error('Past appointments cannot be cancelled.', 422);

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'cancellation_reason' => $request->cancellation_reason,
        ]);

        return $this->success(
            $appointment->load(['client', 'provider', 'service', 'cancelledBy']),
            'Appointment cancelled successfully.'
        );
    }

    /* ═══════════════════════════════════════════════════════════
       5. RESCHEDULE APPOINTMENT
       PUT /api/appointments/{id}/reschedule
    ═══════════════════════════════════════════════════════════ */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        if ($validator->fails())
            return $this->validationError($validator->errors());

        $old = Appointment::find($id);

        if (!$old)
            return $this->error('Appointment not found.', 404);

        if (!in_array(Auth::id(), [$old->client_id, $old->provider_id]))
            return $this->error('You are not authorized to reschedule this appointment.', 403);

        if (!in_array($old->status, ['pending', 'upcoming']))
            return $this->error('Only pending or upcoming appointments can be rescheduled.', 422);

        DB::beginTransaction();

        try {
            // ✅ Correct overlap + lockForUpdate
            $conflict = Appointment::where('provider_id', $old->provider_id)
                ->where('appointment_date', $request->appointment_date)
                ->whereIn('status', ['pending', 'upcoming'])
                ->where('id', '!=', $id)
                ->where(function ($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                        ->where('end_time', '>', $request->start_time);
                })
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                DB::rollBack();
                return $this->error('This time slot is already booked. Please choose another time.', 409);
            }

            // Purani → rescheduled
            $old->update([
                'status' => 'rescheduled',
                'rescheduled_at' => now(),
            ]);

            // Nayi appointment — same details, naya date/time for the histroy + reschedule tracking thats why we make new  one 
            $new = Appointment::create([
                ...$old->only([
                    'client_id',
                    'provider_id',
                    'service_id',
                    'full_name',
                    'reason',
                    'event_location_name',
                    'event_address',
                    'event_distance_km',
                    'event_image',
                    'notes',
                ]),
                'appointment_date' => $request->appointment_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'status' => 'pending',
                'rescheduled_from_id' => $old->id,
                'rescheduled_at' => now(),
            ]);

            DB::commit();

            return $this->success(
                $new->load(['client', 'provider', 'service', 'rescheduledFrom']),
                'Appointment rescheduled successfully.'
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->serverError($e);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       6. START CALL
       PUT /api/appointments/{id}/start-call
    ═══════════════════════════════════════════════════════════ */
    public function startCall(int $id): JsonResponse
    {
        $appointment = Appointment::with('service')->find($id);

        if (!$appointment)
            return $this->error('Appointment not found.', 404);

        if (!in_array(Auth::id(), [$appointment->client_id, $appointment->provider_id]))
            return $this->error('You are not authorized.', 403);

        if (!$appointment->isCallBased())
            return $this->error('This appointment is not a call type.', 422);

        if ($appointment->status !== 'upcoming')
            return $this->error('Only upcoming appointments can be started.', 422);

        if ($appointment->call_started_at)
            return $this->error('Call has already been started.', 422);

        // ✅ 10 min pehle se call start ho sakti hai
        $scheduledStart = Carbon::parse(
            $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->start_time
        );
        if (now()->lt($scheduledStart->subMinutes(10)))
            return $this->error('Call cannot be started more than 10 minutes before scheduled time.', 422);

        $appointment->update([
            'call_started_at' => now(),
            'call_channel_id' => 'ch_' . $appointment->id . '_' . time(),
        ]);

        return $this->success([
            'appointment' => $appointment->fresh()->load('service'),
            'channel_id' => $appointment->call_channel_id,
            'call_type' => $appointment->service->type,
        ], 'Call started successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       7. END CALL
       PUT /api/appointments/{id}/end-call
    ═══════════════════════════════════════════════════════════ */
    public function endCall(int $id): JsonResponse
    {
        $appointment = Appointment::with('service')->find($id);

        if (!$appointment)
            return $this->error('Appointment not found.', 404);

        if (!in_array(Auth::id(), [$appointment->client_id, $appointment->provider_id]))
            return $this->error('You are not authorized.', 403);

        if (!$appointment->isCallBased())
            return $this->error('This appointment is not a call type.', 422);

        if (!$appointment->call_started_at)
            return $this->error('Call has not been started yet.', 422);

        if ($appointment->call_ended_at)
            return $this->error('Call has already been ended.', 422);

        $duration = now()->diffInSeconds($appointment->call_started_at);

        $appointment->update([
            'call_ended_at' => now(),
            'call_duration_seconds' => $duration,
            'status' => 'completed',
        ]);

        return $this->success([
            'appointment' => $appointment->fresh()->load('service'),
            'call_duration_seconds' => $duration,
            'call_duration_minutes' => round($duration / 60, 1),
        ], 'The consultation session has ended.');
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

    private function serverError(\Throwable $e): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong. Please try again.',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}