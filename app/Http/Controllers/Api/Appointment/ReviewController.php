<?php

namespace App\Http\Controllers\api\Appointment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Appointment;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Http\JsonResponse;


class ReviewController extends Controller
{
    /* ═══════════════════════════════════════════════════════════
      1. SUBMIT REVIEW
      POST /api/appointments/{id}/review
   ═══════════════════════════════════════════════════════════ */
    public function store(Request $request, int $appointmentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['nullable', 'string', 'max:1000'],
            'would_recommend' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails())
            return $this->validationError($validator->errors());

        $appointment = Appointment::with('service')->find($appointmentId);

        if (!$appointment)
            return $this->error('Appointment not found.', 404);

        if ($appointment->client_id !== Auth::id())
            return $this->error('Only the client can submit a review.', 403);

        if ($appointment->status !== 'completed')
            return $this->error('You can only review completed appointments.', 422);

        if ($appointment->review)
            return $this->error('You have already submitted a review for this appointment.', 422);

        $review = Review::create([
            'appointment_id' => $appointment->id,
            'service_id' => $appointment->service_id,
            'reviewer_id' => Auth::id(),
            'reviewee_id' => $appointment->provider_id,
            'rating' => $request->rating,
            'review_text' => $request->review_text,
            'would_recommend' => $request->would_recommend,
        ]);

        return $this->success(
            $review->load(['reviewer', 'reviewee', 'appointment', 'service']),
            'Review submitted successfully.',
            201
        );
    }

    /* ═══════════════════════════════════════════════════════════
       2. USER REVIEWS (provider ki saari reviews)
       GET /api/users/{userId}/reviews
    ═══════════════════════════════════════════════════════════ */
    public function userReviews(int $userId): JsonResponse
    {
        $reviews = Review::where('reviewee_id', $userId)
            ->with([
                'reviewer:id,first_name,last_name,profile_picture',
                'service:id,type,title',
                'appointment:id,appointment_date',
            ])
            ->orderByDesc('created_at')
            ->paginate(10);

        $stats = Review::where('reviewee_id', $userId)
            ->selectRaw('AVG(rating) as average_rating, COUNT(*) as total_reviews')
            ->first();

        return $this->success([
            'reviews' => $reviews,
            'average_rating' => $stats && $stats->average_rating ? round($stats->average_rating, 1) : 0,
            'total_reviews' => $stats->total_reviews ?? 0,
        ], 'Reviews fetched successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       3. SERVICE REVIEWS
       GET /api/services/{serviceId}/reviews
    ═══════════════════════════════════════════════════════════ */
    public function serviceReviews(int $serviceId): JsonResponse
    {
        $reviews = Review::where('service_id', $serviceId)
            ->with([
                'reviewer:id,first_name,last_name,profile_picture',
                'appointment:id,appointment_date',
            ])
            ->orderByDesc('created_at')
            ->paginate(10);

        $stats = Review::where('service_id', $serviceId)
            ->selectRaw('AVG(rating) as average_rating, COUNT(*) as total_reviews')
            ->first();

        return $this->success([
            'reviews' => $reviews,
            'average_rating' => $stats && $stats->average_rating ? round($stats->average_rating, 1) : 0,
            'total_reviews' => $stats->total_reviews ?? 0,
        ], 'Service reviews fetched successfully.');
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
