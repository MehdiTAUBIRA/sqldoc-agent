<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use App\Http\Middleware\EnsureAgentAuthenticated;

$storagePath = $_ENV['SQLINFO_STORAGE_PATH'] ?? getenv('SQLINFO_STORAGE_PATH');

if ($storagePath) {

    $requiredDirs = [
        $storagePath,
        $storagePath . '/framework',
        $storagePath . '/framework/sessions',
        $storagePath . '/framework/views',
        $storagePath . '/framework/cache',
        $storagePath . '/framework/cache/data',
        $storagePath . '/logs',
        $storagePath . '/app',
        $storagePath . '/app/public',
    ];

    foreach ($requiredDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    // ✅ MÉTHODE OFFICIELLE LARAVEL 10/11
    $_ENV['LARAVEL_STORAGE_PATH'] = $storagePath;
    putenv("LARAVEL_STORAGE_PATH={$storagePath}");

    if (!defined('LARAVEL_STORAGE_PATH')) {
        define('LARAVEL_STORAGE_PATH', $storagePath);
    }
}

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'project.permissions' => \App\Http\Middleware\CheckProjectPermission::class,
            'agent.auth' => \App\Http\Middleware\EnsureAgentAuthenticated::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            if ($request->header('X-Inertia')) {
                return false;
            }

            return $request->is('api/*') || $request->expectsJson();
        });

        // Logger spécifique aux requêtes Inertia
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->header('X-Inertia')) {
                Log::error('❌ EXCEPTION IN INERTIA REQUEST', [
                    'url' => $request->url(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace_preview' => array_slice(
                        explode("\n", $e->getTraceAsString()),
                        0,
                        10
                    ),
                ]);
            }
        });
    })

    ->create();

