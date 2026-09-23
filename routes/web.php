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
use App\Http\Controllers\ChecklistController as CleaningChecklistController;
use App\Http\Controllers\DashboardController as CleaningDashboardController;
use App\Http\Controllers\ManageSessionController as CleaningManageSessionController;
use App\Http\Controllers\ProfileController as CleaningProfileController;
use App\Http\Controllers\PropertyController as CleaningPropertyController;
use App\Http\Controllers\SessionController as CleaningSessionController;
use App\Http\Controllers\SettingsController as CleaningSettingsController;
use App\Http\Controllers\UserController as CleaningUserController;
use App\Http\Controllers\CalendarController as CleaningCalendarController;
use App\Http\Controllers\SessionReportController as CleaningSessionReportController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
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

// Cleaning-ported auth extras. Cleaning's login, logout, and registration
// routes remain intentionally unregistered; root's AuthController is the
// single login entry point.
Route::middleware('guest')->group(function () {
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    Route::get('force-password-change', [ForcePasswordChangeController::class, 'show'])
        ->name('password.force-change');
    Route::post('force-password-change', [ForcePasswordChangeController::class, 'store'])
        ->name('password.force-change.store');
});
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
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class);
        Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::get('early-access-leads', [EarlyAccessLeadController::class, 'index'])->name('early-access-leads.index');
        Route::post('early-access-leads/{lead}/mark-contacted', [EarlyAccessLeadController::class, 'markContacted'])->name('early-access-leads.mark-contacted');
    });

    // ─── Activity Logs ────────────────────────────────────────────────────────
    Route::middleware('role:admin,manager')->group(function () {
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
if (!function_exists('serveVideoStream')) {
    function serveVideoStream($fullPath, $mimeType) {
        $size = filesize($fullPath);
        $start = 0;
        $end = $size - 1;
        $length = $size;
        $status = 200;
        $headers = [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
        ];


        header("Content-Type: $mimeType");
        header("Accept-Ranges: bytes");
        header("Cache-Control: no-cache, private");
        header("Content-Encoding: identity");

        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_start = $start;
            $c_end = $end;

            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $start-$end/$size");
                exit;
            }
            if ($range == '-') {
                $c_start = $size - substr($range, 1);
            } else {
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1]) && $range[1] !== '') ? $range[1] : $c_end;
            }
            $c_end = ($c_end > $end) ? $end : $c_end;
            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $start-$end/$size");
                exit;
            }
            $start = $c_start;
            $end = $c_end;
            $length = $end - $start + 1;

            header('HTTP/1.1 206 Partial Content');
            header("Content-Length: " . $length);
            header("Content-Range: bytes $start-$end/" . $size);
        } else {
            $length = $size;
            header("Content-Length: " . $length);
        }

        $stream = @fopen($fullPath, 'rb');
        if (!$stream) {
            header('HTTP/1.1 500 Internal Server Error');
            exit;
        }

        fseek($stream, $start);
        $buffer = 1024 * 64; // 64kb
        $i = $start;
        set_time_limit(0);

        while (!feof($stream) && $i <= $end && !connection_aborted()) {
            $bytesToRead = $buffer;
            if (($i + $bytesToRead) > $end) {
                $bytesToRead = $end - $i + 1;
            }
            $data = fread($stream, $bytesToRead);
            echo $data;
            flush();
            $i += $bytesToRead;
        }
        fclose($stream);
        exit;
    }
}
Route::get('/file/{path}', function (string $path) {
    if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        abort(404);
    }
    $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);

    $mimeType = \Illuminate\Support\Facades\File::mimeType($fullPath);
    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

    if ($extension === 'mp4' && $mimeType !== 'video/mp4') {
        $mimeType = 'video/mp4';
    } elseif ($extension === 'webm' && $mimeType !== 'video/webm') {
        $mimeType = 'video/webm';
    } elseif ($extension === 'mov') {
        $mimeType = 'video/quicktime';
    }

    if (str_starts_with($mimeType, 'video/')) {
        return serveVideoStream($fullPath, $mimeType);
    }

    return response()->file($fullPath, [
        'Content-Type' => $mimeType,
        'Accept-Ranges' => 'bytes'
    ]);
})->where('path', '.*');
// Serve storage files through Laravel (bypasses symlink issues on shared hosting)
Route::get('/storage/{path}', function (string $path) {
    if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        abort(404);
    }
    $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);

    $mimeType = \Illuminate\Support\Facades\File::mimeType($fullPath);
    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

    if ($extension === 'mp4' && $mimeType !== 'video/mp4') {
        $mimeType = 'video/mp4';
    } elseif ($extension === 'webm' && $mimeType !== 'video/webm') {
        $mimeType = 'video/webm';
    } elseif ($extension === 'mov') {
        $mimeType = 'video/quicktime';
    }

    if (str_starts_with($mimeType, 'video/')) {
        return serveVideoStream($fullPath, $mimeType);
    }

    return response()->file($fullPath, [
        'Content-Type' => $mimeType,
        'Accept-Ranges' => 'bytes'
    ]);
})->where('path', '.*')->name('storage.serve');

Route::get('/reports/sessions/{token}', [CleaningSessionReportController::class, 'show'])
    ->where('token', '[0-9a-z]+')
    ->name('reports.sessions.show');
Route::get('/reports/sessions/{token}/photos.zip', [CleaningSessionReportController::class, 'photosZip'])
    ->where('token', '[0-9a-z]+')
    ->name('reports.sessions.photos.zip');
Route::get('/reports/sessions/{token}/photos/download/{path}', [CleaningSessionReportController::class, 'photoDownload'])
    ->where([
        'token' => '[0-9a-z]+',
        'path' => '.*',
    ])
    ->name('reports.sessions.photo.download');
Route::get('/reports/sessions/{token}/photos/{path}', [CleaningSessionReportController::class, 'photo'])
    ->where([
        'token' => '[0-9a-z]+',
        'path' => '.*',
    ])
    ->name('reports.sessions.photo');

Route::get('/dashboard', CleaningDashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

// Serve FFmpeg assets through Laravel to ensure correct application/wasm MIME type
Route::get('/ffmpeg-assets/{filename}', function ($filename) {
    $fullPath = public_path("vendor/ffmpeg/{$filename}");
    if (!file_exists($fullPath)) {
        abort(404);
    }

    $mimeType = 'application/octet-stream';
    if (str_ends_with($filename, '.wasm')) {
        $mimeType = 'application/wasm';
    } elseif (str_ends_with($filename, '.js')) {
        $mimeType = 'application/javascript';
    }

    return response()->file($fullPath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000'
    ]);
})->where('filename', '.*')->name('ffmpeg.assets');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [CleaningProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [CleaningProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/preferences', [CleaningProfileController::class, 'updatePreferences'])->name('profile.preferences.update');
    Route::delete('/profile', [CleaningProfileController::class, 'destroy'])->name('profile.destroy');

    // Properties
    Route::resource('properties', CleaningPropertyController::class);
    Route::post('properties/{property}/activate', [CleaningPropertyController::class, 'activate'])->name('properties.activate');

    // Rooms within properties
    Route::get('properties/{property}/rooms', [CleaningPropertyController::class, 'rooms'])->name('properties.rooms.index');
    Route::put('properties/{property}/rooms/{room}', [CleaningPropertyController::class, 'updateRoom'])->name('properties.rooms.update');
    Route::delete('properties/{property}/rooms/{room}', [CleaningPropertyController::class, 'destroyRoom'])->name('properties.rooms.destroy');

    // Tasks within rooms
    Route::get('properties/{property}/rooms/{room}/tasks', [CleaningPropertyController::class, 'tasks'])->name('properties.tasks.index');
    Route::post('properties/{property}/rooms/{room}/tasks', [CleaningPropertyController::class, 'storeTask'])->name('properties.tasks.store');
    Route::post('properties/{property}/rooms/{room}/tasks/bulk', [CleaningPropertyController::class, 'bulkStoreTask'])->name('properties.tasks.bulk-store');
    Route::put('properties/{property}/rooms/{room}/tasks/{task}', [CleaningPropertyController::class, 'updateTask'])->name('properties.tasks.update');
    Route::delete('properties/{property}/rooms/{room}/tasks/{task}', [CleaningPropertyController::class, 'detachTask'])->name('properties.tasks.detach');

    // Property-level tasks
    Route::get('properties/{property}/tasks', [CleaningPropertyController::class, 'propertyTasks'])->name('properties.property-tasks.index');
    Route::post('properties/{property}/tasks', [CleaningPropertyController::class, 'storePropertyTask'])->name('properties.property-tasks.store');
    Route::put('properties/{property}/tasks/{task}', [CleaningPropertyController::class, 'updatePropertyTask'])->name('properties.property-tasks.update');
    Route::delete('properties/{property}/tasks/{task}', [CleaningPropertyController::class, 'detachPropertyTask'])->name('properties.property-tasks.detach');

    // Property Notification Settings
    Route::get('properties/{property}/notifications/settings', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'getSettings'])->name('properties.notifications.settings');
    Route::put('properties/{property}/notifications/settings', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'updateSettings'])->name('properties.notifications.update-settings');
    Route::post('properties/{property}/notifications/recipients', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'addRecipient'])->name('properties.notifications.add-recipient');
    Route::put('properties/{property}/notifications/recipients/{recipient}', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'updateRecipient'])->name('properties.notifications.update-recipient');
    Route::delete('properties/{property}/notifications/recipients/{recipient}', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'deleteRecipient'])->name('properties.notifications.delete-recipient');
    Route::get('properties/{property}/notifications/history', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'history'])->name('properties.notifications.history');
    Route::post('properties/{property}/notifications/logs/{log}/resend', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'resend'])->name('properties.notifications.resend');

    // Housekeeper Sessions
    Route::get('/assignments', [\App\Http\Controllers\AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/sessions', [CleaningSessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/{session}', [CleaningSessionController::class, 'show'])->name('sessions.show');
    Route::get('/sessions/{session}/training-required', [CleaningSessionController::class, 'trainingRequired'])->name('sessions.training_required');
    Route::post('/sessions/{session}/start', [CleaningSessionController::class, 'start'])->name('sessions.start');
    Route::post('/sessions/{session}/complete-onboarding', [CleaningSessionController::class, 'completeOnboarding'])->name('sessions.complete-onboarding');
    Route::post('/sessions/{session}/gps-override', [CleaningSessionController::class, 'grantGpsOverride'])->name('sessions.gps-override');
    Route::post('/sessions/{session}/complete', [CleaningSessionController::class, 'complete'])->name('sessions.complete');
    Route::post('/sessions/{session}/admin-close', [CleaningSessionController::class, 'adminClose'])->name('sessions.admin-close');
    Route::post('/sessions/{session}/advance-stage', [CleaningSessionController::class, 'advanceStage'])->name('sessions.advance-stage');
    Route::post('/sessions/{session}/go-back-stage', [CleaningSessionController::class, 'goBackStage'])->name('sessions.go-back-stage');
    Route::post('/sessions/{session}/save-note', [CleaningSessionController::class, 'saveNote'])->name('sessions.save-note');


    // Checklist data endpoint
    Route::get('/sessions/{session}/data', [CleaningSessionController::class, 'getData'])->name('sessions.data');
    // Instructions endpoint
    Route::get('/sessions/{session}/instructions', [CleaningSessionController::class, 'getInstructions'])->name('sessions.instructions');
    // Legacy API path kept for compatibility
    Route::get('/api/sessions/{session}/data', [CleaningSessionController::class, 'getData'])->name('sessions.data.api');
    Route::post('/sessions/{session}/rooms/{room}/tasks/{task}/toggle', [CleaningChecklistController::class, 'toggle'])->name('checklist.toggle');
    Route::post('/sessions/{session}/rooms/{room}/tasks/{task}/note', [CleaningChecklistController::class, 'note'])->name('checklist.note');
    Route::post('/sessions/{session}/rooms/{room}/tasks/{task}/photo', [CleaningChecklistController::class, 'taskPhoto'])->name('checklist.photo');
    Route::delete('/sessions/{session}/rooms/{room}/tasks/{task}/photo/{photo}', [CleaningChecklistController::class, 'deletePhoto'])->name('checklist.photo.delete');
    Route::post('/sessions/{session}/rooms/{room}/tasks/{task}/view-instruction', [CleaningChecklistController::class, 'viewInstruction'])->name('checklist.view-instruction');

    // Property-level checklist interactions (no room)
    Route::post('/sessions/{session}/tasks/{task}/toggle', [CleaningChecklistController::class, 'togglePropertyTask'])->name('checklist.property-task.toggle');
    Route::post('/sessions/{session}/tasks/{task}/note', [CleaningChecklistController::class, 'notePropertyTask'])->name('checklist.property-task.note');
    Route::post('/sessions/{session}/tasks/{task}/photo', [CleaningChecklistController::class, 'propertyTaskPhoto'])->name('checklist.property-task.photo');
    Route::delete('/sessions/{session}/tasks/{task}/photo/{photo}', [CleaningChecklistController::class, 'deletePropertyTaskPhoto'])->name('checklist.property-task.photo.delete');
    Route::post('/sessions/{session}/tasks/{task}/view-instruction', [CleaningChecklistController::class, 'viewPropertyTaskInstruction'])->name('checklist.property-task.view-instruction');

    // Add Session Routes (Admin/Owner)
    Route::get('/manage/sessions', [CleaningManageSessionController::class, 'index'])->name('manage.sessions.index');
    Route::get('/manage/sessions/create', [CleaningManageSessionController::class, 'create'])->name('manage.sessions.create');
    Route::get('/reports/training', [\App\Http\Controllers\TrainingReportController::class, 'index'])->name('reports.training.index');
    Route::get('/reports/familiarity', [\App\Http\Controllers\FamiliarityReportController::class, 'index'])->name('reports.familiarity.index');
    Route::post('/manage/sessions', [CleaningManageSessionController::class, 'store'])->name('manage.sessions.store');
    Route::get('/manage/sessions/{session}/edit', [CleaningManageSessionController::class, 'edit'])->name('manage.sessions.edit');
    Route::put('/manage/sessions/{session}', [CleaningManageSessionController::class, 'update'])->name('manage.sessions.update');
    Route::delete('/manage/sessions/{session}', [CleaningManageSessionController::class, 'destroy'])->name('manage.sessions.destroy');
    Route::get('/api/properties/{property}/sporadic-tasks', [CleaningManageSessionController::class, 'getSporadicTasks'])->name('api.properties.sporadic-tasks');

    // Calendar Routes
    Route::get('/calendar', [CleaningCalendarController::class, 'index'])->name('calendar.index');
    Route::get('/api/calendar/events', [CleaningCalendarController::class, 'events'])->name('api.calendar.events');

    // Training Routes
    Route::get('/training', [\App\Http\Controllers\TrainingController::class, 'index'])->name('training.index');
    Route::get('/training/{type}/{id}', [\App\Http\Controllers\TrainingController::class, 'show'])->name('training.show');
    Route::post('/training/{type}/{id}/progress', [\App\Http\Controllers\TrainingController::class, 'updateProgress'])->name('training.update_progress');

    // User Management Routes
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [\App\Http\Controllers\UserController::class, 'create'])->name('users.create');
    Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [\App\Http\Controllers\UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/deactivate', [\App\Http\Controllers\UserController::class, 'deactivate'])
        ->middleware('role:admin')
        ->name('users.deactivate');
    Route::post('/users/{user}/reactivate', [\App\Http\Controllers\UserController::class, 'reactivate'])
        ->middleware('role:admin')
        ->name('users.reactivate');

    Route::post('/users/{user}/familiarity/reset', [\App\Http\Controllers\UserFamiliarityController::class, 'resetAll'])->name('users.familiarity.reset_all');
    Route::post('/users/{user}/familiarity/reset/{task}', [\App\Http\Controllers\UserFamiliarityController::class, 'resetTask'])->name('users.familiarity.reset_task');

    // Final Additional Routes to fix 500 errors
    Route::post('properties/{property}/duplicate', [\App\Http\Controllers\PropertyDuplicateController::class, 'store'])->name('properties.duplicate');
    // Properties -> Rooms sorting
    Route::match(['post', 'patch'], 'properties/{property}/rooms/order', [\App\Http\Controllers\PropertyRoomOrderController::class, 'update'])->name('properties.rooms.order');

    // Properties -> Property Tasks sorting
    Route::match(['post', 'patch'], 'properties/{property}/tasks/order', [\App\Http\Controllers\PropertyTaskOrderController::class, 'update'])->name('properties.property-tasks.order');
    Route::post('properties/{property}/rooms', [\App\Http\Controllers\PropertyRoomController::class, 'store'])->name('properties.rooms.store');
    Route::post('properties/{property}/rooms/attach', [\App\Http\Controllers\PropertyRoomAttachController::class, 'store'])->name('properties.rooms.attach');
    Route::get('api/rooms/suggest', [\App\Http\Controllers\RoomSuggestionController::class, 'index'])->name('rooms.suggest');

    Route::match(['post', 'patch'], 'rooms/{room}/tasks/order', [\App\Http\Controllers\RoomTaskOrderController::class, 'updateForRoom'])->name('rooms.tasks.order');
    Route::post('rooms/{room}/tasks/attach', [\App\Http\Controllers\RoomTaskAttachController::class, 'store'])->name('rooms.tasks.attach');
    Route::delete('rooms/{room}/tasks/{task}', [\App\Http\Controllers\RoomController::class, 'detachTask'])->name('rooms.tasks.detach');
    Route::get('api/tasks/suggest', [\App\Http\Controllers\TaskSuggestionController::class, 'index'])->name('tasks.suggest');

    Route::get('settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::post('settings', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');
    Route::get('activity', [\App\Http\Controllers\ActivityController::class, 'index'])->name('activity.index');

    // Photo Management
    Route::post('sessions/{session}/rooms/{room}/photos', [\App\Http\Controllers\PhotoController::class, 'store'])->name('photos.store');
    Route::delete('sessions/{session}/photos/{photo}', [\App\Http\Controllers\PhotoController::class, 'destroy'])->name('photos.destroy');

    // Task Media Management
    Route::post('tasks/{task}/media', [\App\Http\Controllers\TaskMediaController::class, 'store'])->name('tasks.media.store');
    Route::delete('tasks/{task}/media/{media}', [\App\Http\Controllers\TaskMediaController::class, 'destroy'])->name('tasks.media.destroy');

    // API for dynamic form loading
    Route::get('api/properties/{property}/rooms', [\App\Http\Controllers\PropertyAssignmentsApiController::class, 'rooms'])->name('api.properties.rooms');
    Route::get('api/properties/{property}/tasks', [\App\Http\Controllers\PropertyAssignmentsApiController::class, 'propertyTasks'])->name('api.properties.tasks');
    Route::get('api/properties/{property}/assigned-rooms', [\App\Http\Controllers\PropertyAssignmentsApiController::class, 'assignedRooms'])->name('api.properties.assigned-rooms');
    Route::get('api/properties/{property}/assigned-property-tasks', [\App\Http\Controllers\PropertyAssignmentsApiController::class, 'assignedPropertyTasks'])->name('api.properties.assigned-property-tasks');

    // Global Room/Task Management
    Route::post('rooms/bulk-attach-tasks', [\App\Http\Controllers\RoomController::class, 'bulkAttachTasks'])->name('rooms.bulk-attach-tasks');

    // Room Task Management Routes
    Route::get('rooms/{room}/tasks', [\App\Http\Controllers\RoomController::class, 'tasks'])->name('rooms.tasks.index');
    Route::post('rooms/{room}/tasks', [\App\Http\Controllers\RoomController::class, 'storeTask'])->name('rooms.tasks.store');
    Route::get('rooms/{room}/tasks/{task}/edit', [\App\Http\Controllers\RoomController::class, 'editTask'])->name('rooms.tasks.edit');
    Route::put('rooms/{room}/tasks/{task}', [\App\Http\Controllers\RoomController::class, 'updateTask'])->name('rooms.tasks.update');
    Route::post('rooms/{room}/tasks/bulk', [\App\Http\Controllers\RoomController::class, 'bulkStoreTask'])->name('rooms.tasks.bulk-store');

    Route::resource('rooms', \App\Http\Controllers\RoomController::class);
    Route::resource('tasks', \App\Http\Controllers\TaskController::class);

    // Resources Pages (Photos, Videos, Guides)
    Route::get('resources/photos', [\App\Http\Controllers\ResourcePageController::class, 'photos'])->name('resources.photos');
    Route::get('resources/videos', [\App\Http\Controllers\ResourcePageController::class, 'videos'])->name('resources.videos');
    Route::get('resources/guides', [\App\Http\Controllers\ResourcePageController::class, 'guides'])->name('resources.guides');

    // Admin Instructional Video Management
    Route::get('admin/videos', [\App\Http\Controllers\InstructionalVideoController::class, 'index'])->name('admin.videos.index');
    Route::get('admin/videos/create', [\App\Http\Controllers\InstructionalVideoController::class, 'create'])->name('admin.videos.create');
    Route::post('admin/videos', [\App\Http\Controllers\InstructionalVideoController::class, 'store'])->name('admin.videos.store');
    Route::get('admin/videos/{video}/edit', [\App\Http\Controllers\InstructionalVideoController::class, 'edit'])->name('admin.videos.edit');
    Route::post('admin/videos/{video}', [\App\Http\Controllers\InstructionalVideoController::class, 'update'])->name('admin.videos.update');
    Route::delete('admin/videos/{video}', [\App\Http\Controllers\InstructionalVideoController::class, 'destroy'])->name('admin.videos.destroy');
    Route::post('admin/videos/{video}/publish', [\App\Http\Controllers\InstructionalVideoController::class, 'togglePublish'])->name('admin.videos.publish');

    // Cleaner API endpoint for property videos
    Route::get('api/property-videos', [\App\Http\Controllers\ResourcesController::class, 'getPropertyVideos'])->name('api.property-videos');
});

Route::get('/scan-images-now', function () {
    echo "<pre style='background:#111; color:#0f0; padding:20px; font-family:monospace;'>";
    echo "=== cPANEL DEEP FILE SCANNER ===\n\n";

    $targetFile = 'kVNfagaJs1LZTG3qNCRpZtF4eI3roD2ga71aUmXF.png';
    $searchDir = '/home/dzm'; // The root of their cPanel

    echo "Scanning the entire cPanel account ($searchDir) for '$targetFile'...\n";
    echo "This might take a few seconds...\n\n";

    $cmd = "find " . escapeshellarg($searchDir) . " -name " . escapeshellarg($targetFile) . " 2>/dev/null";
    // Since shell_exec is disabled, we use PHP's RecursiveDirectoryIterator
    $foundPaths = [];
    try {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($searchDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        foreach ($iterator as $file) {
            // Skip massive directories to prevent timeouts
            $path = $file->getPathname();
            if ($file->isDir()) {
                $dirName = $file->getFilename();
                if (in_array($dirName, ['vendor', 'node_modules', '.git', 'cache', 'sessions', 'logs', 'framework'])) {
                    continue;
                }
            } else {
                if ($file->getFilename() === $targetFile) {
                    $foundPaths[] = $path;
                }
            }
        }
    } catch (\Exception $e) {
        // Ignore permission denied errors
    }

    if (!empty($foundPaths)) {
        echo "<strong style='color:#ffeb3b; font-size:18px;'>[FOUND IT!] The files are hiding here:</strong>\n\n";
        foreach ($foundPaths as $fp) {
            echo $fp . "\n";
        }
        echo "\nSOLUTION: Copy all images from that folder into the new storage/app/public/task-media/ folder.\n";
    } else {
        echo "<strong style='color:red'>[NOT FOUND]</strong>\n";
    }
    echo "</pre>";
});
