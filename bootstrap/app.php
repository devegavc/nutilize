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
            'redirect.authenticated' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        ]);

        $middleware->web(prepend: [
            \App\Http\Middleware\RejectUnsafeQueryValues::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleDatabaseErrors::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException|\PDOException $e, Request $request) {
            // #region agent log
            file_put_contents(base_path('debug-6ca79f.log'), json_encode([
                'sessionId' => '6ca79f',
                'runId' => 'pre-fix',
                'hypothesisId' => 'D',
                'location' => 'bootstrap/app.php:render',
                'message' => 'query exception rendered as database error page',
                'data' => [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'sqlstate' => $e instanceof QueryException ? ($e->errorInfo[0] ?? null) : null,
                    'driverCode' => $e instanceof QueryException ? ($e->errorInfo[1] ?? null) : null,
                    'exceptionClass' => $e::class,
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ]).PHP_EOL, FILE_APPEND);
            // #endregion
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
                    $offlineMessage = 'Database is temporarily unreachable on this network. Please switch DNS/network or try again.';

                    if ($request->expectsJson()) {
                        return response()->json(['message' => $offlineMessage], 503);
                    }

                    return response()->view('errors.database-offline', [
                        'message' => $offlineMessage,
                    ], 503);
                }
            }

            $publicMessage = 'A database error occurred. Please try again later.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $publicMessage], 500);
            }

            return response()->view('errors.database-error', [
                'message' => $publicMessage,
            ], 500);
        });
    })->create();
