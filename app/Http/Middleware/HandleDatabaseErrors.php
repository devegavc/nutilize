<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

class HandleDatabaseErrors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (QueryException|PDOException $e) {
            // #region agent log
            file_put_contents(base_path('debug-6ca79f.log'), json_encode([
                'sessionId' => '6ca79f',
                'runId' => 'pre-fix',
                'hypothesisId' => 'D',
                'location' => 'HandleDatabaseErrors.php:handle',
                'message' => 'middleware caught database exception',
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
            // Log the error
            $driverMessage = $e instanceof QueryException
                ? ($e->errorInfo[2] ?? $e->getMessage())
                : $e->getMessage();

            \Log::error('Database query failed', [
                'sqlstate' => $e instanceof QueryException ? ($e->errorInfo[0] ?? $e->getCode()) : $e->getCode(),
                'message' => $driverMessage,
                'url' => $request->path(),
            ]);

            // Check if it's a network/connection error
            if ($this->isNetworkError($e)) {
                return response()->view('errors.database-offline', [
                    'message' => 'Database connection unavailable. Please check your network connection.',
                ], 503);
            }

            $publicMessage = 'A database error occurred. Please try again later.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $publicMessage], 500);
            }

            return response()->view('errors.database-error', [
                'message' => $publicMessage,
            ], 500);
        }
    }

    /**
     * Check if this is a network/connection error.
     */
    private function isNetworkError($exception): bool
    {
        $message = $exception->getMessage();
        $networkErrors = [
            'could not translate host name',
            'Unknown host',
            'Connection refused',
            'Connection timed out',
            'Network is unreachable',
            'SQLSTATE[HY000]',
            'SQLSTATE[08006]',
        ];

        foreach ($networkErrors as $error) {
            if (stripos($message, $error) !== false) {
                return true;
            }
        }

        return false;
    }
}
