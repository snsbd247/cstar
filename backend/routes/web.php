<?php

use Illuminate\Support\Facades\Route;

// Public website (Laravel Blade, decision D1) — built in Sprint 5.
Route::get('/', function () {
    return view('welcome');
});

// React staff & parent apps. The production build lives in public/spa (see frontend/vite.config.ts);
// every client-side route of those apps returns the same index.html.
Route::get('/{area}/{path?}', function () {
    $index = public_path('spa/index.html');
    abort_unless(is_file($index), 404, 'Frontend not built. Run "npm run build" in the frontend folder.');

    return response()->file($index, ['Cache-Control' => 'no-cache, no-store, must-revalidate']);
})->where(['area' => 'app|trainer|therapist|portal|login|change-password', 'path' => '.*']);
