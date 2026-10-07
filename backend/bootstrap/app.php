<?php

use App\Http\Middleware\ApplySecuritySettings;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA cookie auth: the React app is served from the same origin as /api.
        $middleware->statefulApi();
        // Session timeout from Settings → Security must be known before the session starts.
        $middleware->prepend(ApplySecuritySettings::class);
        // Payment gateways post back from their own sites (no CSRF token); those routes verify with the gateway.
        $middleware->validateCsrfTokens(except: ['pay/sslcommerz/*', 'pay/test/*']);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// cPanel layout (docs/C-STAR-Deployment-BN.md): the web root is public_html, outside the app folder.
// The release package writes its relative path into .public-path so asset, upload and SPA paths point there.
if (is_file($marker = dirname(__DIR__).'/.public-path') && ($web = realpath(dirname(__DIR__).'/'.trim((string) file_get_contents($marker)))) && is_dir($web)) {
    $app->usePublicPath($web);
}

return $app;
