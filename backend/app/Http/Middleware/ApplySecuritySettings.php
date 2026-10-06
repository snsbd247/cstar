<?php

namespace App\Http\Middleware;

use App\Services\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan §২১ Security: runs before the session starts so Settings → Security decides how long an idle
 * sign-in lasts, and adds the browser security headers to every response.
 */
class ApplySecuritySettings
{
    public function handle(Request $request, Closure $next): Response
    {
        // Live site on https:// — any plain-http request is sent to the secure address first.
        if (app()->isProduction() && str_starts_with((string) config('app.url'), 'https://') && ! $request->isSecure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        config(['session.lifetime' => (int) SystemSettings::safe('security', 'session_timeout_minutes')]);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($request->is('api/*')) {
            // Patient data must never sit in a shared or browser cache.
            $headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
