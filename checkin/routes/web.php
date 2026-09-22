<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EarlyAccessLeadController;
use App\Http\Controllers\Admin\GuestNoticeController;
use App\Http\Controllers\Admin\InstructionStepController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\NotificationSettingsController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\PropertyAvailabilityController;
use App\Http\Controllers\Admin\PropertyLockController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChannexWebhookController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EarlyAccessController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\PrivacyRequestController;
use App\Http\Controllers\SeamWebhookController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TelnyxWebhookController;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Property;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [EarlyAccessController::class, 'show'])->name('early-access');
Route::get('/terms', [LegalPageController::class, 'terms'])->name('legal.terms');
Route::get('/privacy-policy', [LegalPageController::class, 'privacyPolicy'])->name('legal.privacy');
Route::get('/rental-contract', [LegalPageController::class, 'rentalContract'])->name('legal.rental-contract');
Route::get('/privacy-request', [PrivacyRequestController::class, 'index'])->name('privacy-request');
Route::post('/privacy-request', [PrivacyRequestController::class, 'store'])->name('privacy-request.store');
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
Route::post('/early-access', [EarlyAccessController::class, 'store'])->name('early-access.store');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/webhooks/seam', [SeamWebhookController::class, 'handle'])->name('webhooks.seam');
Route::post('/webhooks/channex', [ChannexWebhookController::class, 'handle'])->name('webhooks.channex');
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
Route::post('/webhooks/telnyx/sms', [TelnyxWebhookController::class, 'handle'])->name('webhooks.telnyx.sms');

Route::get('/checkin', [GuestController::class, 'checkinByReservation'])->name('checkin.rid');
Route::post('/checkin/verify', [GuestController::class, 'verifyReservationLogin'])->name('checkin.verify');

Route::prefix('guest/{booking_id}/{token}')->name('guest.')->group(function () {
    Route::get('/', [GuestController::class, 'show'])->name('show');
    Route::post('/identity', [GuestController::class, 'submitIdentity'])->name('identity');
    Route::post('/arrival-agree', [GuestController::class, 'agreeToArrival'])->name('arrival-agree');
    Route::get('/rental-agreement', [GuestController::class, 'rentalAgreement'])->name('rental-agreement');
    Route::get('/rental-agreement/download', [GuestController::class, 'rentalAgreementPdf'])->name('rental-agreement.pdf');
    Route::post('/vehicle-info', [GuestController::class, 'submitVehicleInfo'])->name('vehicle-info');
    Route::post('/login', [GuestController::class, 'login'])->name('login');
    Route::post('/sign-rental-agreement', [GuestController::class, 'signRentalAgreement'])->name('sign-rental-agreement');
    Route::post('/parking', [GuestController::class, 'parking'])->name('parking');
    Route::post('/verify-gps', [GuestController::class, 'verifyGps'])->name('gps');
    Route::post('/confirm-checkin', [GuestController::class, 'confirmCheckin'])->name('confirm-checkin');
    Route::post('/confirm-checkout', [GuestController::class, 'confirmCheckout'])->name('confirm-checkout');
    Route::post('/deposit/intent', [GuestController::class, 'createDepositIntent'])->name('deposit.intent');
    Route::post('/deposit/platform', [GuestController::class, 'selectPlatformPayment'])->name('deposit.platform');
    Route::post('/deposit/confirm', [GuestController::class, 'confirmDepositPayment'])->name('deposit.confirm');
    Route::post('/charge/intent', [GuestController::class, 'createChargeIntent'])->name('charge.intent');
    Route::post('/charge/confirm', [GuestController::class, 'confirmChargePayment'])->name('charge.confirm');
    Route::post('/unlock-door/{lock}', [GuestController::class, 'unlockDoor'])->name('unlock-door');
    Route::post('/lock-door/{lock}', [GuestController::class, 'lockDoor'])->name('lock-door');
    Route::get('/lock-status/{lock}', [GuestController::class, 'lockStatus'])->name('lock-status');
    Route::get('/gps-status', [GuestController::class, 'gpsStatus'])->name('gps-status');
    Route::get('/id-status', [GuestController::class, 'idStatus'])->name('id-status');
    Route::get('/guide/{category:slug}', [GuestController::class, 'category'])->name('category');
    Route::get('/guide/{category:slug}/events', [GuestController::class, 'moreEvents'])->name('category.events');
});

Route::middleware(['auth', 'role'])->prefix('admin')->name('admin.')->group(function () {

    // ─── Dashboard ───────────────────────────────────────────────────────────
    Route::get('/', DashboardController::class)->name('dashboard');

    // ─── Tour ────────────────────────────────────────────────────────────────
    Route::post('tour/complete', function () {
        request()->user()->forceFill(['admin_tour_completed_at' => now()])->save();
        ActivityLogService::admin('tour_completed', request()->user()->name.' completed the admin onboarding tour.', 'users', ['severity' => 'success']);

        return response()->json(['ok' => true]);
    })->name('tour.complete');

    Route::post('tour/restart', function () {
        request()->user()->forceFill(['admin_tour_completed_at' => null])->save();
        ActivityLogService::admin('tour_restarted', request()->user()->name.' restarted the admin tour.', 'users');

        return back()->with('success', 'The interactive tour will appear again on your next visit.');
    })->name('tour.restart');

    Route::post('tour/dashboard/complete', function () {
        request()->user()->forceFill(['dashboard_tour_completed_at' => now()])->save();

        return response()->json(['ok' => true]);
    })->name('tour.dashboard.complete');

    Route::post('tour/dashboard/restart', function () {
        request()->user()->forceFill(['dashboard_tour_completed_at' => null])->save();

        return response()->json(['ok' => true]);
    })->name('tour.dashboard.restart');

    // ─── Static pages ────────────────────────────────────────────────────────
    Route::view('guide', 'admin.guide')->name('guide');
    Route::view('security', 'admin.security')->name('security');

    // ─── Guest Guide (per-property categories) ──────────────────────────────
    Route::get('guest-guide', [PropertyController::class, 'guideIndex'])->name('guest-guide.index');
    Route::get('properties/{property}/categories', [PropertyController::class, 'guide'])->name('guest-guide.show');
    Route::post('properties/{property}/guide/copy', [PropertyController::class, 'copyGuide'])->name('guest-guide.copy');

    // ─── Properties ──────────────────────────────────────────────────────────
    Route::resource('properties', PropertyController::class)->except(['show']);

    // ─── Guests / Bookings ────────────────────────────────────────────────────
    Route::get('guests/search', [BookingController::class, 'searchLive'])->name('guests.search');
    Route::get('guests/this-week', [BookingController::class, 'thisWeekMore'])->name('guests.this-week');
    Route::get('guests/today', [BookingController::class, 'todayMore'])->name('guests.today');
    Route::get('guests/upcoming', [BookingController::class, 'upcomingMore'])->name('guests.upcoming');
    // 'edit' intentionally excluded: editing an existing guest now happens
    // inline on the show page via the expandable Guest Details panel
    // (task: "merge edit and view page into one clean UI"), not a separate
    // page. 'create'/'store' remain -- a brand-new booking still needs its
    // own page since there's no existing record to expand yet.
    Route::resource('guests', BookingController::class)->parameters(['guests' => 'booking'])->except(['edit']);
    Route::put('guests/{booking}/ledger', [BookingController::class, 'updateLedger'])->name('guests.ledger.update');
    Route::get('guests/{booking}/preview/{state}', [BookingController::class, 'preview'])->name('guests.preview');
    Route::post('guests/{booking}/override-checkin', [BookingController::class, 'overrideCheckin'])->name('guests.override');
    Route::post('guests/{booking}/override-checkout', [BookingController::class, 'overrideCheckout'])->name('guests.override-checkout');
    Route::post('guests/{booking}/override-gps', [BookingController::class, 'overrideGps'])->name('guests.override-gps');
    Route::post('guests/{booking}/archive', [BookingController::class, 'archive'])->name('guests.archive');
    Route::post('guests/{booking}/unarchive', [BookingController::class, 'unarchive'])->name('guests.unarchive');
    Route::post('guests/{booking}/mark-id', [BookingController::class, 'markIdReceived'])->name('guests.mark-id');
    Route::post('guests/{booking}/bypass-vehicle-info', [BookingController::class, 'bypassVehicleInfo'])->name('guests.bypass-vehicle-info');
    Route::post('guests/{booking}/approve', [BookingController::class, 'approveBooking'])->name('guests.approve');
    Route::post('guests/{booking}/background-check', [BookingController::class, 'markBackgroundCheckComplete'])->name('guests.background-check');
    Route::post('guests/{booking}/deposit-verified', [BookingController::class, 'markDepositVerified'])->name('guests.deposit-verified');
    Route::post('guests/{booking}/approve-checkin', [BookingController::class, 'approveCheckin'])->name('guests.approve-checkin');
    Route::post('guests/{booking}/update-status', [BookingController::class, 'updateStatus'])->name('guests.update-status');
    Route::post('guests/{booking}/id/{side}/approve', [BookingController::class, 'approveIdSide'])->whereIn('side', ['front', 'back'])->name('guests.id.approve');
    Route::post('guests/{booking}/id/{side}/decline', [BookingController::class, 'declineIdSide'])->whereIn('side', ['front', 'back'])->name('guests.id.decline');
    Route::post('guests/{booking}/time-preference/{type}', [BookingController::class, 'updateTimePreferenceStatus'])->whereIn('type', ['checkin', 'checkout'])->name('guests.time-preference.update');
    Route::post('guests/{booking}/block-access', [BookingController::class, 'blockAccess'])->name('guests.block-access');
    Route::post('guests/{booking}/unblock-access', [BookingController::class, 'unblockAccess'])->name('guests.unblock-access');
    Route::put('guests/{booking}/welcome-message', [BookingController::class, 'updateWelcomeMessage'])->name('guests.welcome-message');
    Route::get('guests/{booking}/photo-id', [BookingController::class, 'photoId'])->name('guests.photo-id');
    Route::get('guests/{booking}/photo-id-back', [BookingController::class, 'photoIdBack'])->name('guests.photo-id-back');
    Route::get('guests/{booking}/photo-id/view', [BookingController::class, 'photoIdView'])->name('guests.photo-id-view');
    Route::get('guests/{booking}/photo-id-back/view', [BookingController::class, 'photoIdBackView'])->name('guests.photo-id-back-view');
    Route::get('guests/{booking}/license-plate', [BookingController::class, 'licensePlate'])->name('guests.license-plate');
    Route::get('guests/{booking}/license-plate/view', [BookingController::class, 'licensePlateView'])->name('guests.license-plate-view');

    // ─── Categories ───────────────────────────────────────────────────────────
    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::post('categories/assign', [CategoryController::class, 'assign'])->name('categories.assign');
    Route::get('categories/{category}/preview/{property}', [CategoryController::class, 'preview'])->name('categories.preview');

    // ─── Content / Pages ──────────────────────────────────────────────────────
    Route::get('content/{property}/{category}/edit', [ContentController::class, 'editPage'])->name('content.edit');
    Route::put('content/{property}/{category}', [ContentController::class, 'updatePage'])->name('content.update');
    Route::post('content/{property}/{category}/unlink', [ContentController::class, 'unlinkPage'])->name('content.unlink');
    Route::put('content/{property}/{category}/assignment', [ContentController::class, 'updateAssignment'])->name('content.assignment.update');
    Route::get('content/{property}/amenities', [ContentController::class, 'amenitiesIndex'])->name('amenities.index');
    Route::get('content/{property}/amenities/create', [ContentController::class, 'createAmenity'])->name('amenities.create');
    Route::post('content/{property}/amenities', [ContentController::class, 'storeAmenity'])->name('amenities.store');
    Route::get('amenities/{amenity}/edit', [ContentController::class, 'editAmenity'])->name('amenities.edit');
    Route::put('amenities/{amenity}', [ContentController::class, 'updateAmenity'])->name('amenities.update');
    Route::delete('amenities/{amenity}', [ContentController::class, 'deleteAmenity'])->name('amenities.destroy');

    // ─── Instruction Steps ───────────────────────────────────────────────────────
    Route::get('properties/{property}/steps', [InstructionStepController::class, 'forProperty'])->name('instructions.show');
    Route::post('instructions/reorder', [InstructionStepController::class, 'reorder'])->name('instructions.reorder');
    Route::delete('instructions/images/{image}', [InstructionStepController::class, 'destroyImage'])->name('instructions.images.destroy');
    Route::resource('instructions', InstructionStepController::class)->except(['show']);

    // ─── Media Library ───────────────────────────────────────────────────────────
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::get('media/picker', [MediaController::class, 'picker'])->name('media.picker');
    Route::post('media/folders', [MediaController::class, 'storeFolder'])->name('media.folders.store');
    Route::delete('media/folders/{folder}', [MediaController::class, 'destroyFolder'])->name('media.folders.destroy');
    Route::post('media/files', [MediaController::class, 'storeFile'])->name('media.files.store');
    Route::delete('media/files/{file}', [MediaController::class, 'destroyFile'])->name('media.files.destroy');
    Route::post('properties/{property}/duplicate', [PropertyController::class, 'duplicate'])->name('properties.duplicate');
    Route::post('properties/{property}/locks', [PropertyLockController::class, 'store'])->name('properties.locks.store');
    Route::put('properties/{property}/locks/{lock}', [PropertyLockController::class, 'update'])->name('properties.locks.update');
    Route::delete('properties/{property}/locks/{lock}', [PropertyLockController::class, 'destroy'])->name('properties.locks.destroy');

    Route::get('properties/{property}/availability', [PropertyAvailabilityController::class, 'index'])->name('properties.availability.index');
    Route::post('properties/{property}/availability/fetch-mapping', [PropertyAvailabilityController::class, 'fetchMapping'])->name('properties.availability.fetch-mapping');
    Route::post('properties/{property}/availability/save-mapping', [PropertyAvailabilityController::class, 'saveMapping'])->name('properties.availability.save-mapping');
    Route::post('properties/{property}/availability/save-ical-url', [PropertyAvailabilityController::class, 'saveIcalUrl'])->name('properties.availability.save-ical-url');
    Route::post('properties/{property}/availability/import-ical', [PropertyAvailabilityController::class, 'importIcal'])->name('properties.availability.import-ical');
    Route::post('properties/{property}/availability/push-to-channex', [PropertyAvailabilityController::class, 'pushToChannex'])->name('properties.availability.push-to-channex');
    Route::post('media/bulk-move', [MediaController::class, 'bulkMove'])->name('media.bulk-move');
    Route::delete('media/bulk-delete', [MediaController::class, 'bulkDelete'])->name('media.bulk-delete');

    // ─── Settings ─────────────────────────────────────────────────────────────
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('settings/legal', [SettingsController::class, 'legalEdit'])->name('settings.legal.edit');
    Route::put('settings/legal', [SettingsController::class, 'legalUpdate'])->name('settings.legal.update');
    Route::get('settings/notifications', [NotificationSettingsController::class, 'edit'])->name('settings.notifications.edit');
    Route::put('settings/notifications', [NotificationSettingsController::class, 'update'])->name('settings.notifications.update');

    // ─── Guest Notices (conditional pop-ups / check-in steps) ──────────────
    Route::resource('notices', GuestNoticeController::class)->except(['show']);

    // ─── Notification bell ──────────────────────────────────────────────────
    Route::post('notifications/dismiss', [NotificationController::class, 'dismiss'])->name('notifications.dismiss');
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

    // ─── Users / Team ─────────────────────────────────────────────────────────
    Route::middleware('role:owner')->group(function () {
        Route::resource('users', UserController::class);
        Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::get('early-access-leads', [EarlyAccessLeadController::class, 'index'])->name('early-access-leads.index');
        Route::post('early-access-leads/{lead}/mark-contacted', [EarlyAccessLeadController::class, 'markContacted'])->name('early-access-leads.mark-contacted');
    });

    // ─── Activity Logs ────────────────────────────────────────────────────────
    Route::middleware('role:owner,manager')->group(function () {
        Route::get('logs', [LogController::class, 'index'])->name('logs.index');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('logs/{log}', [LogController::class, 'show'])->name('logs.show');
    });

    // ─── Global Search ────────────────────────────────────────────────────────
    Route::get('search', function (Request $request) {
        $q = trim($request->q ?? '');
        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $guests = Booking::with('property')
            ->where(fn ($query) => $query
                ->where('guest_name', 'like', "%{$q}%")
                ->orWhere('booking_id', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
            )->take(5)->get()
            ->map(fn ($b) => [
                'type' => 'Guest',
                'label' => $b->guest_name.' ('.$b->booking_id.')',
                'url' => route('admin.guests.show', $b),
            ]);

        $properties = Property::where('name', 'like', "%{$q}%")->take(4)->get()
            ->map(fn ($p) => [
                'type' => 'Property',
                'label' => $p->name,
                'url' => route('admin.properties.edit', $p),
            ]);

        $categories = Category::where('title', 'like', "%{$q}%")->take(3)->get()
            ->map(fn ($c) => [
                'type' => 'Category',
                'label' => $c->title,
                'url' => route('admin.categories.edit', $c),
            ]);

        $users = User::where(fn ($query) => $query
            ->where('name', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%")
        )->take(3)->get()
            ->map(fn ($u) => [
                'type' => 'User',
                'label' => $u->name.' ('.$u->email.')',
                'url' => route('admin.users.show', $u),
            ]);

        return response()->json([
            'results' => $guests->concat($properties)->concat($categories)->concat($users)->values(),
        ]);
    })->name('search');
});
Route::get('/img/{path}', [ImageController::class, 'show'])->where('path', '.*');
