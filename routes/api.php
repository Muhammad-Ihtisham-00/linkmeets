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
use App\Http\Controllers\Api\Marketplace\MarketplaceController;


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

    Route::prefix('marketplace')->group(function () {

        // Public - view all products
        Route::get('/products', [MarketplaceController::class, 'index']);
        Route::get('/products/{productId}', [MarketplaceController::class, 'show']);

        Route::get('/my-products', [MarketplaceController::class, 'myProducts']);
        Route::post('/products/create', [MarketplaceController::class, 'create']);
        Route::post('/products/{productId}/update', [MarketplaceController::class, 'update']);
        Route::delete('/products/{productId}/delete', [MarketplaceController::class, 'delete']);
    });
});
