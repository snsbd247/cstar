<?php

use App\Http\Controllers\Website\EnquiryController;
use App\Http\Controllers\Website\WebsiteController;
use Illuminate\Support\Facades\Route;

// Public website (Laravel Blade, decision D1)
Route::controller(WebsiteController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/about', 'about')->name('about');
    Route::get('/services', 'services')->name('services');
    Route::get('/services/{slug}', 'service')->name('service');
    Route::get('/therapists', 'therapists')->name('therapists');
    Route::get('/therapists/{slug}', 'therapist')->name('therapist');
    Route::get('/trainers', 'trainers')->name('trainers');
    Route::get('/branches', 'branches')->name('branches');
    Route::get('/gallery', 'gallery')->name('gallery');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/notices', 'notices')->name('notices');
    Route::get('/notices/{slug}', 'notice')->name('notice');
    Route::get('/sitemap.xml', 'sitemap')->name('sitemap');
    Route::get('/robots.txt', 'robots');
});

Route::controller(EnquiryController::class)->group(function () {
    Route::get('/appointment', 'appointmentForm')->name('appointment');
    Route::post('/appointment', 'storeAppointment')->middleware('throttle:website-forms')->name('appointment.store');
    Route::get('/appointment/thank-you', 'appointmentThanks')->name('appointment.thanks');
    Route::get('/contact', 'contactForm')->name('contact');
    Route::post('/contact', 'storeContact')->middleware('throttle:website-forms')->name('contact.store');
});

// React staff & parent apps. The production build lives in public/spa (see frontend/vite.config.ts);
// every client-side route of those apps returns the same index.html.
Route::get('/{area}/{path?}', function () {
    $index = public_path('spa/index.html');
    abort_unless(is_file($index), 404, 'Frontend not built. Run "npm run build" in the frontend folder.');

    return response()->file($index, ['Cache-Control' => 'no-cache, no-store, must-revalidate']);
})->where(['area' => 'app|trainer|therapist|portal|login|change-password', 'path' => '.*']);
