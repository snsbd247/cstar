<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** React staff & parent apps: every client-side route returns the built index.html (public/spa). */
class SpaController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $index = public_path('spa/index.html');
        abort_unless(is_file($index), 404, 'Frontend not built. Run "npm run build" in the frontend folder.');

        return response()->file($index, ['Cache-Control' => 'no-cache, no-store, must-revalidate']);
    }
}
