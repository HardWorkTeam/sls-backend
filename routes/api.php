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
use App\Http\Controllers\PublicStatsController;
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
Route::post('/auth/reset-password/status', [AuthController::class, 'resetPasswordStatus'])->middleware('throttle:auth');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');
Route::post('/auth/email/resend', [AuthController::class, 'resendEmailVerification'])->middleware('throttle:auth');
Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::prefix('public')->group(function () {
    Route::get('/invitations/{code}', [PublicInvitationController::class, 'show']);
    Route::post('/invitations/{code}/rsvp', [PublicInvitationController::class, 'rsvp'])
        ->middleware('throttle:20,1');

    // Catalog for the marketing site (sls-web). No auth: the controllers fall
    // back to active-only records when there is no authenticated super admin.
    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/invitation-templates', [InvitationTemplateController::class, 'index']);

    // Headline platform stats for the marketing site (e.g. total couples).
    Route::get('/stats', [PublicStatsController::class, 'index']);
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
            Route::post('/subscriptions/{subscription}/revert', [AdminSubscriptionController::class, 'revert']);
        });
    });

    // Weddings: listing/creation, then everything nested under a wedding
    // the user can access (creator, member, or super admin).
    Route::get('/weddings', [WeddingController::class, 'index']);
    Route::post('/weddings', [WeddingController::class, 'store'])
        ->middleware('role:couple');

    // `scopeBindings()` resolves every nested {model} through its parent
    // wedding's relation (e.g. `$wedding->guests()->findOrFail($id)`), so a
    // record from another wedding 404s during binding instead of relying on
    // each controller action to re-check `wedding_id` by hand. Route parameter
    // names must match the relation Laravel infers from them —
    // {guestGroup} → guestGroups(), {timelineEvent} → timelineEvents(),
    // {rsvpResponse} → rsvpResponses(), {table} → tables(), and so on.
    Route::prefix('weddings/{wedding}')->middleware('wedding.access')->scopeBindings()->group(function () {
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
        Route::put('/members/{member}', [WeddingMemberController::class, 'update']);
        Route::delete('/members/{member}', [WeddingMemberController::class, 'destroy'])
            ->middleware('role:super_admin,organizer,couple');

        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'store']);
        Route::get('/invitations/{invitation}', [InvitationController::class, 'show']);
        Route::put('/invitations/{invitation}', [InvitationController::class, 'update']);
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy']);
        Route::post('/invitations/{invitation}/publish', [InvitationController::class, 'publish']);
        Route::post('/invitations/{invitation}/unpublish', [InvitationController::class, 'unpublish']);
        Route::get('/invitations/{invitation}/qr', [InvitationController::class, 'qrCode']);

        Route::get('/guest-groups', [GuestGroupController::class, 'index']);
        Route::post('/guest-groups', [GuestGroupController::class, 'store']);
        Route::put('/guest-groups/{guestGroup}', [GuestGroupController::class, 'update']);
        Route::delete('/guest-groups/{guestGroup}', [GuestGroupController::class, 'destroy']);

        Route::get('/guests', [GuestController::class, 'index']);
        Route::post('/guests', [GuestController::class, 'store']);
        Route::delete('/guests', [GuestController::class, 'destroyAll']);
        Route::post('/guests/import', [GuestController::class, 'import']);
        Route::get('/guests/export', [GuestController::class, 'export']);
        Route::post('/guests/bulk-invite', [GuestController::class, 'bulkInvite']);

        // Wedding-day QR check-in — gated to plans that include it. Static
        // paths are registered before the {guest} wildcard so "check-in" is
        // never captured as a guest id.
        Route::middleware('plan.module:checkin')->group(function () {
            Route::get('/guests/check-in/stats', [GuestController::class, 'checkInStats']);
            Route::post('/guests/check-in', [GuestController::class, 'checkIn']);
            Route::get('/guests/{guest}/qr', [GuestController::class, 'qrCode']);
            Route::post('/guests/{guest}/check-in', [GuestController::class, 'setCheckIn']);
        });

        Route::put('/guests/{guest}', [GuestController::class, 'update']);
        Route::delete('/guests/{guest}', [GuestController::class, 'destroy']);

        // RSVP dashboard — gated to plans that include it.
        Route::middleware('plan.module:rsvp')->group(function () {
            Route::get('/rsvps', [RsvpController::class, 'index']);
            Route::get('/rsvps/stats', [RsvpController::class, 'stats']);
            Route::put('/rsvps/{rsvpResponse}', [RsvpController::class, 'update']);
            Route::delete('/rsvps/{rsvpResponse}', [RsvpController::class, 'destroy']);
        });

        // Seating planner — gated to plans that include it (Premium+).
        Route::middleware('plan.module:seating')->group(function () {
            Route::get('/tables', [SeatingController::class, 'tables']);
            Route::post('/tables', [SeatingController::class, 'storeTable']);
            Route::post('/tables/import', [SeatingController::class, 'import']);
            Route::get('/tables/export', [SeatingController::class, 'export']);
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
            Route::get('/gifts/export', [GiftController::class, 'export']);
            Route::post('/gifts', [GiftController::class, 'store']);
            Route::put('/gifts/{gift}', [GiftController::class, 'update']);
            Route::delete('/gifts/{gift}', [GiftController::class, 'destroy']);
        });

        // Expense tracking — gated to plans that include it (Free+).
        Route::middleware('plan.module:expense')->group(function () {
            Route::get('/expenses', [ExpenseController::class, 'index']);
            Route::get('/expenses/summary', [ExpenseController::class, 'summary']);
            Route::post('/expenses', [ExpenseController::class, 'store']);
            Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
        });

        // Timeline — gated to plans that include it.
        Route::middleware('plan.module:timeline')->group(function () {
            Route::get('/timeline-events', [TimelineEventController::class, 'index']);
            Route::post('/timeline-events', [TimelineEventController::class, 'store']);
            Route::put('/timeline-events/{timelineEvent}', [TimelineEventController::class, 'update']);
            Route::delete('/timeline-events/{timelineEvent}', [TimelineEventController::class, 'destroy']);
        });

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
