<?php

use App\Http\Middleware\EnsurePhysicalFacilitiesAdmin;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'pf-admin' => EnsurePhysicalFacilitiesAdmin::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleDatabaseErrors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $e) {
            // #region agent log
            try {
                $path = '/';
                try { $path = request()->getPathInfo() ?: '/'; } catch (\Throwable) {}
                if ($path === '/' || $path === '') {
                    $payload = json_encode([
                        'sessionId' => 'fa7298',
                        'runId' => 'landing-500',
                        'hypothesisId' => 'A',
                        'location' => 'bootstrap/app.php:report',
                        'message' => 'uncaught exception on landing',
                        'data' => [
                            'class' => $e::class,
                            'error' => $e->getMessage(),
                            'file' => basename($e->getFile()).':'.$e->getLine(),
                        ],
                        'timestamp' => (int) (microtime(true) * 1000),
                    ])."\n";
                    file_put_contents(base_path('debug-fa7298.log'), $payload, FILE_APPEND);
                }
            } catch (\Throwable) {}
            // #endregion
        });

        $exceptions->render(function (QueryException|\PDOException $e, Request $request) {
            $message = $e->getMessage();

            $networkErrors = [
                'could not translate host name',
                'Unknown host',
                'Connection refused',
                'Connection timed out',
                'Network is unreachable',
                'SQLSTATE[08006]',
            ];

            foreach ($networkErrors as $error) {
                if (stripos($message, $error) !== false) {
                    return response()->view('errors.database-offline', [
                        'message' => 'Database is temporarily unreachable on this network. Please switch DNS/network or try again.',
                    ], 503);
                }
            }

            return response()->view('errors.database-error', [
                'message' => 'A database error occurred. Please try again later.',
            ], 500);
        });
    })->create();
