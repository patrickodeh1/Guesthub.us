<?php

use App\Http\Controllers\PublicListingController;
use Illuminate\Support\Facades\Route;

Route::get('/stays', [PublicListingController::class, 'browse'])->name('public.stays.index');
Route::get('/stays/{city}', [PublicListingController::class, 'city'])
    ->where('city', '[A-Za-z0-9-]+')
    ->name('public.stays.city');
Route::get('/stay/{listing_slug}/quote', [PublicListingController::class, 'quote'])
    ->middleware('throttle:30,1')
    ->name('public.listing.quote');
Route::get('/stay/{listing_slug}/availability', [PublicListingController::class, 'availability'])
    ->middleware('throttle:60,1')
    ->name('public.listing.availability');
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
