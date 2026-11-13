<?php

use App\Http\Controllers\CalendarExportController;
use App\Http\Controllers\DirectBookingController;
use App\Http\Controllers\LeaseApplicationController;
use App\Http\Controllers\PublicListingController;
use App\Http\Controllers\StripeConnectController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/ical/{token}.ics', CalendarExportController::class)
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:60,1')
        ->name('ical.export');
    Route::get('/stays', [PublicListingController::class, 'browse'])->name('public.stays.index');
    Route::get('/stays/{city}', [PublicListingController::class, 'city'])
        ->where('city', '[A-Za-z0-9-]+')
        ->name('public.stays.city');
    Route::get('/embed/stay/{listing_slug}', [PublicListingController::class, 'embed'])->middleware('throttle:60,1')->name('public.listing.embed');
    Route::get('/stay/{listing_slug}/quote', [PublicListingController::class, 'quote'])
        ->middleware('throttle:30,1')
        ->name('public.listing.quote');
    Route::get('/stay/{listing_slug}/availability', [PublicListingController::class, 'availability'])
        ->middleware('throttle:60,1')
        ->name('public.listing.availability');
    Route::get('/stay/{listing_slug}/checkout', [DirectBookingController::class, 'checkout'])
        ->name('public.checkout.show');
    Route::post('/stay/{listing_slug}/checkout', [DirectBookingController::class, 'start'])
        ->middleware('throttle:10,1')
        ->name('public.checkout.start');
    Route::post('/stay/checkout/{booking_id}/{token}/payment-report', [\App\Http\Controllers\PaymentReceiptController::class, 'guestReport'])
        ->middleware('throttle:10,1')->name('public.checkout.payment-report');
    Route::get('/stay/checkout/{booking_id}/{token}/receipts/{receipt}', [\App\Http\Controllers\PaymentReceiptController::class, 'guestShow'])
        ->middleware('throttle:30,1')->name('public.checkout.receipt');
    Route::get('/stay/checkout/{booking_id}/{token}', [DirectBookingController::class, 'pay'])
        ->name('public.checkout.pay');
    Route::post('/stay/checkout/{booking_id}/{token}/confirm', [DirectBookingController::class, 'confirm'])
        ->middleware('throttle:20,1')
        ->name('public.checkout.confirm');
    Route::get('/stay/application/{application}/{token}/offer/{offerToken}', [\App\Http\Controllers\LeaseOfferController::class, 'show'])->name('public.lease-offer.show');
    Route::post('/stay/application/{application}/{token}/offer/{offerToken}/sign', [\App\Http\Controllers\LeaseOfferController::class, 'sign'])->middleware('throttle:10,1')->name('public.lease-offer.sign');
    Route::post('/stay/application/{application}/{token}/offer/{offerToken}/decline', [\App\Http\Controllers\LeaseOfferController::class, 'decline'])->middleware('throttle:5,1')->name('public.lease-offer.decline');
    Route::post('/stay/application/{application}/{token}/offer/{offerToken}/confirm-payment', [\App\Http\Controllers\LeaseOfferController::class, 'confirmPayment'])->middleware('throttle:10,1')->name('public.lease-offer.confirm');
    Route::get('/stay/{listing_slug}/apply', [LeaseApplicationController::class, 'apply'])
        ->name('public.lease-application.apply');
    Route::post('/stay/{listing_slug}/apply', [LeaseApplicationController::class, 'storeId'])
        ->middleware('throttle:10,1')
        ->name('public.lease-application.id');
    Route::get('/stay/application/{application}/{token}/id', [LeaseApplicationController::class, 'retryIdForm'])
        ->name('public.lease-application.id.retry');
    Route::post('/stay/application/{application}/{token}/id', [LeaseApplicationController::class, 'retryId'])
        ->middleware('throttle:10,1')
        ->name('public.lease-application.id.retry.store');
    Route::post('/stay/{listing_slug}/apply/photos', [LeaseApplicationController::class, 'storeIdPhotos'])
        ->middleware('throttle:10,1')
        ->name('public.lease-application.id.photos');
    Route::post('/stay/application/{application}/{token}/id/photos', [LeaseApplicationController::class, 'retryIdPhotos'])
        ->middleware('throttle:10,1')
        ->name('public.lease-application.id.retry.photos');
    Route::get('/stay/application/{application}/{token}/about', [LeaseApplicationController::class, 'about'])
        ->name('public.lease-application.about');
    Route::post('/stay/application/{application}/{token}/about', [LeaseApplicationController::class, 'saveAbout'])
        ->middleware('throttle:10,1')
        ->name('public.lease-application.about.store');
    Route::get('/stay/application/{application}/{token}/employment', [LeaseApplicationController::class, 'employment'])
        ->name('public.lease-application.employment');
    Route::post('/stay/application/{application}/{token}/employment', [LeaseApplicationController::class, 'saveEmployment'])
        ->middleware('throttle:10,1')
        ->name('public.lease-application.employment.store');
    Route::get('/stay/application/{application}/{token}/consent', [LeaseApplicationController::class, 'consent'])
        ->name('public.lease-application.consent');
    Route::post('/stay/application/{application}/{token}/submit', [LeaseApplicationController::class, 'submit'])
        ->middleware('throttle:10,1')
        ->name('public.lease-application.submit');
    Route::get('/stay/application/{application}/{token}/fee', [LeaseApplicationController::class, 'fee'])
        ->name('public.lease-application.fee');
    Route::post('/stay/application/{application}/{token}/fee/payment-report', [LeaseApplicationController::class, 'reportFeePayment'])
        ->middleware('throttle:10,1')
        ->name('public.lease-application.payment-report');
    Route::get('/stay/application/{application}/{token}/fee/receipts/{receipt}', [LeaseApplicationController::class, 'feeReceipt'])
        ->middleware('throttle:30,1')
        ->name('public.lease-application.receipt');
    Route::post('/stay/application/{application}/{token}/confirm', [LeaseApplicationController::class, 'confirmFee'])
        ->middleware('throttle:20,1')
        ->name('public.lease-application.confirm');
    Route::post('/stay/application/{application}/{token}/retry-fee', [LeaseApplicationController::class, 'retryFee'])
        ->middleware('throttle:5,1')
        ->name('public.lease-application.retry-fee');
    Route::get('/stay/{listing_slug}', [PublicListingController::class, 'show'])->name('public.listing.show');
    Route::get('/h/{host_slug}', [PublicListingController::class, 'host'])
        ->where('host_slug', '[a-z0-9-]+')
        ->name('public.host.show');
    Route::get('/sitemap.xml', [PublicListingController::class, 'sitemap'])->name('public.sitemap');
    Route::get('/robots.txt', [PublicListingController::class, 'robots'])->name('public.robots');
    Route::get('/llms.txt', [PublicListingController::class, 'llms'])->name('public.llms');
    Route::get('/{key}.txt', [PublicListingController::class, 'indexNowKey'])
        ->where('key', '[A-Za-z0-9_-]{8,128}')
        ->name('public.indexnow.key');
    Route::post('/webhooks/stripe/connect', [StripeConnectController::class, 'webhook'])
        ->name('webhooks.stripe.connect');
});
