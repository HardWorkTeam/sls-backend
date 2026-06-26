<?php

use App\Http\Controllers\Admin\IncomeController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\GuestGroupController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationTemplateController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PublicInvitationController;
use App\Http\Controllers\RsvpController;
use App\Http\Controllers\SeatingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TimelineEventController;
use App\Http\Controllers\WeddingController;
use App\Http\Controllers\WeddingMemberController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn (): JsonResponse => response()->json([
    'ok' => true,
    'service' => config('app.name'),
    'timestamp' => now()->toIso8601String(),
]));

/*
|--------------------------------------------------------------------------
| Public routes (no authentication)
|--------------------------------------------------------------------------
*/
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

Route::prefix('public')->group(function () {
    Route::get('/invitations/{code}', [PublicInvitationController::class, 'show']);
    Route::post('/invitations/{code}/rsvp', [PublicInvitationController::class, 'rsvp'])
        ->middleware('throttle:20,1');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });

    Route::get('/dashboard/overview', [DashboardController::class, 'overview'])
        ->middleware('role:super_admin,organizer');

    Route::get('/dashboard/platform-analytics', [DashboardController::class, 'platformAnalytics'])
        ->middleware('role:super_admin');

    // Catalog: browsable by all authenticated users, managed by super admins.
    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/invitation-templates', [InvitationTemplateController::class, 'index']);

    Route::middleware('role:super_admin')->group(function () {
        Route::post('/packages', [PackageController::class, 'store']);
        Route::put('/packages/{package}', [PackageController::class, 'update']);
        Route::delete('/packages/{package}', [PackageController::class, 'destroy']);
        Route::put('/invitation-templates/{template}', [InvitationTemplateController::class, 'update']);

        Route::prefix('admin')->group(function () {
            Route::get('/roles', [UserController::class, 'roles']);
            Route::apiResource('users', UserController::class);

            Route::get('/income', [IncomeController::class, 'index']);
            Route::get('/income/summary', [IncomeController::class, 'summary']);

            Route::get('/subscriptions', [AdminSubscriptionController::class, 'index']);
            Route::post('/subscriptions/{subscription}/confirm', [AdminSubscriptionController::class, 'confirm']);
        });
    });

    // Weddings: listing/creation, then everything nested under a wedding
    // the user can access (creator, member, or super admin).
    Route::get('/weddings', [WeddingController::class, 'index']);
    Route::post('/weddings', [WeddingController::class, 'store'])
        ->middleware('role:couple');

    Route::prefix('weddings/{wedding}')->middleware('wedding.access')->group(function () {
        Route::get('/', [WeddingController::class, 'show']);
        Route::put('/', [WeddingController::class, 'update']);
        Route::delete('/', [WeddingController::class, 'destroy'])
            ->middleware('role:super_admin,organizer');
        Route::post('/status', [WeddingController::class, 'changeStatus'])
            ->middleware('role:super_admin,organizer,couple');
        Route::get('/dashboard', [WeddingController::class, 'dashboard']);

        Route::middleware('role:super_admin,organizer,couple')->group(function () {
            Route::get('/subscription', [SubscriptionController::class, 'show']);
            Route::post('/subscription', [SubscriptionController::class, 'select']);
            Route::post('/subscription/pay', [SubscriptionController::class, 'pay']);
        });

        Route::get('/members', [WeddingMemberController::class, 'index']);
        Route::post('/members', [WeddingMemberController::class, 'store'])
            ->middleware('role:super_admin,organizer');
        Route::post('/members/invite', [WeddingMemberController::class, 'invite'])
            ->middleware('role:super_admin,organizer,couple');
        Route::delete('/members/{member}', [WeddingMemberController::class, 'destroy'])
            ->middleware('role:super_admin,organizer');

        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'store']);
        Route::get('/invitations/{invitation}', [InvitationController::class, 'show']);
        Route::put('/invitations/{invitation}', [InvitationController::class, 'update']);
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy']);
        Route::post('/invitations/{invitation}/publish', [InvitationController::class, 'publish']);
        Route::get('/invitations/{invitation}/qr', [InvitationController::class, 'qrCode']);

        Route::get('/guest-groups', [GuestGroupController::class, 'index']);
        Route::post('/guest-groups', [GuestGroupController::class, 'store']);
        Route::put('/guest-groups/{group}', [GuestGroupController::class, 'update']);
        Route::delete('/guest-groups/{group}', [GuestGroupController::class, 'destroy']);

        Route::get('/guests', [GuestController::class, 'index']);
        Route::post('/guests', [GuestController::class, 'store']);
        Route::post('/guests/import', [GuestController::class, 'import']);
        Route::get('/guests/export', [GuestController::class, 'export']);
        Route::post('/guests/bulk-invite', [GuestController::class, 'bulkInvite']);
        Route::put('/guests/{guest}', [GuestController::class, 'update']);
        Route::delete('/guests/{guest}', [GuestController::class, 'destroy']);

        Route::get('/rsvps', [RsvpController::class, 'index']);
        Route::get('/rsvps/stats', [RsvpController::class, 'stats']);
        Route::put('/rsvps/{rsvp}', [RsvpController::class, 'update']);
        Route::delete('/rsvps/{rsvp}', [RsvpController::class, 'destroy']);

        // Seating planner — gated to plans that include it (Premium+).
        Route::middleware('plan.module:seating')->group(function () {
            Route::get('/tables', [SeatingController::class, 'tables']);
            Route::post('/tables', [SeatingController::class, 'storeTable']);
            Route::put('/tables/{table}', [SeatingController::class, 'updateTable']);
            Route::delete('/tables/{table}', [SeatingController::class, 'destroyTable']);
            Route::post('/seatings/assign', [SeatingController::class, 'assign']);
            Route::post('/seatings/unassign', [SeatingController::class, 'unassign']);
            Route::post('/seatings/auto', [SeatingController::class, 'autoSeat']);
            Route::get('/seatings/report', [SeatingController::class, 'report']);
        });

        // Gift tracking — gated to plans that include it (Premium+).
        Route::middleware('plan.module:gifts')->group(function () {
            Route::get('/gifts', [GiftController::class, 'index']);
            Route::get('/gifts/summary', [GiftController::class, 'summary']);
            Route::post('/gifts', [GiftController::class, 'store']);
            Route::put('/gifts/{gift}', [GiftController::class, 'update']);
            Route::delete('/gifts/{gift}', [GiftController::class, 'destroy']);
        });

        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::get('/expenses/summary', [ExpenseController::class, 'summary']);
        Route::post('/expenses', [ExpenseController::class, 'store']);
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);

        Route::get('/timeline-events', [TimelineEventController::class, 'index']);
        Route::post('/timeline-events', [TimelineEventController::class, 'store']);
        Route::put('/timeline-events/{event}', [TimelineEventController::class, 'update']);
        Route::delete('/timeline-events/{event}', [TimelineEventController::class, 'destroy']);

        // Photo gallery — gated to plans that include it (Premium+).
        Route::middleware('plan.module:gallery')->group(function () {
            Route::get('/albums', [GalleryController::class, 'albums']);
            Route::post('/albums', [GalleryController::class, 'storeAlbum']);
            Route::put('/albums/{album}', [GalleryController::class, 'updateAlbum']);
            Route::delete('/albums/{album}', [GalleryController::class, 'destroyAlbum']);
            Route::get('/media', [GalleryController::class, 'media']);
            Route::post('/media', [GalleryController::class, 'upload']);
            Route::patch('/media/{mediaItem}', [GalleryController::class, 'updateMedia']);
            Route::delete('/media/{mediaItem}', [GalleryController::class, 'destroyMedia']);
        });
    });
});
