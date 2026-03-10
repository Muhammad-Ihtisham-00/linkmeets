<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\api\Social\PostController;
use App\Http\Controllers\Api\Profile\EmailController;
use App\Http\Controllers\Api\Profile\GalleryController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\Social\RelationController;
use App\Http\Controllers\Api\Profile\UsernameController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Profile\IntroVideoController;
use App\Http\Controllers\api\Social\PostCommentController;
use App\Http\Controllers\Api\Profile\BusinessCardController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Profile\ChangePasswordController;
use App\Http\Controllers\Api\Profile\PrivacySettingController;
use App\Http\Controllers\Api\Profile\ProfilePictureController;
use App\Http\Controllers\Api\Appointment\AppointmentController;
use App\Http\Controllers\Api\Appointment\ServiceController;
use App\Http\Controllers\Api\Appointment\ReviewController;

use App\Http\Controllers\Api\Chat\ConversationController;
use App\Http\Controllers\Api\Chat\MessageController;
use App\Http\Controllers\Api\Chat\BlockReportController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Password Reset Routes
Route::prefix('password')->group(function () {

    Route::post('/forgot', [PasswordResetController::class, 'sendResetCode']);
    Route::post('/verify-code', [PasswordResetController::class, 'verifyResetCode']);
    Route::post('/reset', [PasswordResetController::class, 'resetPassword']);
});

// Email Verification Routes
Route::prefix('email')->group(function () {

    Route::get('/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');

    Route::post('/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware(['auth:sanctum', 'throttle:6,1'])
        ->name('verification.send');
});



Route::middleware('auth:sanctum', 'verified')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    // My Profile Routes
    Route::prefix('my-profile')->group(function () {

        // General Profile Info
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);

        // Change Password
        Route::post('/change-password', [ChangePasswordController::class, 'change']);

        // Email
        Route::post('/email/change', [EmailController::class, 'change']);

        // Username
        Route::post('/username/check', [UsernameController::class, 'checkAvailability']);
        Route::post('/username/change', [UsernameController::class, 'change']);

        // Profile Picture
        Route::post('/picture/upload', [ProfilePictureController::class, 'upload']);

        // Intro Video
        Route::post('/intro-video/upload', [IntroVideoController::class, 'upload']);
        Route::get('/intro-video', [IntroVideoController::class, 'show']);
        Route::delete('/intro-video', [IntroVideoController::class, 'delete']);

        // Gallery
        Route::get('/gallery', [GalleryController::class, 'index']);
        Route::post('/gallery/upload', [GalleryController::class, 'upload']);
        Route::delete('/gallery/delete', [GalleryController::class, 'delete']);

        // Business Card
        Route::get('/business-card', [BusinessCardController::class, 'show']);
        Route::post('/business-card', [BusinessCardController::class, 'update']);

        // Privacy Settings
        Route::get('/privacy-settings', [PrivacySettingController::class, 'show']);
        Route::post('/privacy-settings', [PrivacySettingController::class, 'update']);
    });

    // Social
    Route::prefix('social')->group(function () {

        // Relationships / friends
        Route::prefix('relations')->group(function () {
            Route::post('follow/{userId}', [RelationController::class, 'follow']);
            Route::post('unfollow/{userId}', [RelationController::class, 'unfollow']);
            Route::post('block/{userId}', [RelationController::class, 'block']);
            Route::post('unblock/{userId}', [RelationController::class, 'unblock']);

            Route::get('following/{userId}', [RelationController::class, 'following']);
            Route::get('followers/{userId}', [RelationController::class, 'followers']);

            Route::get('my-following', [RelationController::class, 'myFollowing']);
            Route::get('my-followers', [RelationController::class, 'myFollowers']);
            Route::get('my-blocked', [RelationController::class, 'myBlocked']);
        });

        // Posts
        Route::prefix('posts')->group(function () {

            Route::post('/create', [PostController::class, 'create']);
            Route::get('/show/{postId}', [PostController::class, 'show']);
            Route::delete('/delete/{postId}', [PostController::class, 'delete']);
            Route::post('{postId}/share', [PostController::class, 'share']);

            // Increment view count
            Route::post('/increment-view/{postId}', [PostController::class, 'incrementView']);

            // Likes
            Route::post('/like/{postId}', [PostController::class, 'toggleLike']);
            Route::get('/likes/{postId}', [PostController::class, 'likes']);

            //Comments
            Route::post('{postId}/comments/', [PostCommentController::class, 'store']);
            Route::get('{postId}/comments/', [PostCommentController::class, 'index']);
            Route::post('/comments/{commentId}/like', [PostCommentController::class, 'toggleLike']);
            Route::delete('/comments/{commentId}', [PostCommentController::class, 'delete']);
            Route::get('/comments/{commentId}/replies', [PostCommentController::class, 'replies']);
            Route::get('/comments/{commentId}/likes', [PostCommentController::class, 'likes']);
        });

        // Reviews
        Route::prefix('reviews')->group(function () {
            // review endpoints
        });
    });
});


// for appointments and reviews, we will create separate controllers and routes later when we implement those features.

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes are protected with Sanctum auth middleware
| Header required: Authorization: Bearer {token}
|              + : Accept: application/json
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // ══════════════════════════════════════════════════════════════
    // SERVICES
    // ══════════════════════════════════════════════════════════════
    Route::prefix('services')->group(function () {

        Route::get('/', [ServiceController::class, 'index']);    // My services
        Route::post('/', [ServiceController::class, 'store']);    // Create service
        Route::get('/{id}', [ServiceController::class, 'show']);     // Service detail
        Route::put('/{id}', [ServiceController::class, 'update']);   // Update service
        Route::put('/{id}/toggle', [ServiceController::class, 'toggle']);   // On/Off toggle
        Route::delete('/{id}', [ServiceController::class, 'destroy']);  // Delete service

    });

    // ══════════════════════════════════════════════════════════════
    // APPOINTMENTS
    // ══════════════════════════════════════════════════════════════
    Route::prefix('appointments')->group(function () {

        Route::get('/', [AppointmentController::class, 'index']);       // My appointments list
        Route::post('/', [AppointmentController::class, 'store']);       // Book appointment
        Route::get('/{id}', [AppointmentController::class, 'show']);        // Detail
        Route::put('/{id}/cancel', [AppointmentController::class, 'cancel']);      // Cancel
        Route::put('/{id}/reschedule', [AppointmentController::class, 'reschedule']);  // Reschedule
        Route::put('/{id}/start-call', [AppointmentController::class, 'startCall']);   // Start call
        Route::put('/{id}/end-call', [AppointmentController::class, 'endCall']);     // End call

        // Review submit (appointment complete hone k baad)
        Route::post('/{id}/review', [ReviewController::class, 'store']);            // Submit review

    });

    // ══════════════════════════════════════════════════════════════
    // USER SPECIFIC
    // ══════════════════════════════════════════════════════════════
    Route::prefix('users/{userId}')->group(function () {

        Route::get('services', [ServiceController::class, 'userServices']);  // User ki active services
        Route::get('reviews', [ReviewController::class, 'userReviews']);   // User ki saari reviews

    });

    // ══════════════════════════════════════════════════════════════
    // SERVICE REVIEWS
    // ══════════════════════════════════════════════════════════════
    Route::get('services/{serviceId}/reviews', [ReviewController::class, 'serviceReviews']); // Service ki reviews

});




// ═════════════════════════════════════════════════════════════
// CHAT & CONVERSATIONS
// ═════════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->group(function () {

    // ══════════════════════════════════════════════════════════════
    // CONVERSATIONS
    // ══════════════════════════════════════════════════════════════
    Route::prefix('conversations')->group(function () {

        Route::get('/', [ConversationController::class, 'index']);           // My chat list
        Route::post('/private', [ConversationController::class, 'startPrivate']);    // Start private chat
        Route::post('/group', [ConversationController::class, 'createGroup']);     // Create group
        Route::get('/{id}', [ConversationController::class, 'show']);            // Conversation detail
        Route::put('/{id}/group', [ConversationController::class, 'updateGroup']);     // Update group name/image
        Route::post('/{id}/participants', [ConversationController::class, 'addParticipants']); // Add members to group
        Route::delete('/{id}/leave', [ConversationController::class, 'leave']);           // Leave group

        // ── Messages ─────────────────────────────────────────────
        Route::get('/{id}/messages', [MessageController::class, 'index']);               // Get messages
        Route::post('/{id}/messages', [MessageController::class, 'store']);               // Send message
        Route::put('/{id}/read', [MessageController::class, 'markAsRead']);          // Mark as read ✓✓

    });

    // ══════════════════════════════════════════════════════════════
    // MESSAGES
    // ══════════════════════════════════════════════════════════════
    Route::prefix('messages')->group(function () {

        Route::delete('/{id}', [MessageController::class, 'destroy']);             // Delete message
        Route::put('/{id}/live-location', [MessageController::class, 'updateLiveLocation']);  // Update live location
        Route::delete('/{id}/live-location', [MessageController::class, 'stopLiveLocation']);    // Stop live location

    });

    // ══════════════════════════════════════════════════════════════
    // BLOCK & REPORT
    // ══════════════════════════════════════════════════════════════
    Route::prefix('users')->group(function () {

        Route::get('/blocked', [BlockReportController::class, 'blockedList']);     // My blocked list
        Route::post('/{id}/block', [BlockReportController::class, 'block']);           // Block user
        Route::delete('/{id}/block', [BlockReportController::class, 'unblock']);         // Unblock user
        Route::post('/{id}/report', [BlockReportController::class, 'report']);          // Report user

    });

});