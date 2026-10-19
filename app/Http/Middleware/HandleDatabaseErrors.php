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
            // Log the error
            $driverMessage = $e instanceof QueryException
                ? ($e->errorInfo[2] ?? $e->getMessage())
                : $e->getMessage();

            \Log::error('Database query failed', [
                'sqlstate' => $e instanceof QueryException ? ($e->errorInfo[0] ?? $e->getCode()) : $e->getCode(),
                'message' => $driverMessage,
                'url' => $request->path(),
            ]);

            // #region agent log
            try {
                $authUser = $request->user();
                file_put_contents(base_path('debug-28e049.log'), json_encode([
                    'sessionId' => '28e049',
                    'runId' => 'user-repro',
                    'hypothesisId' => 'H1',
                    'location' => 'HandleDatabaseErrors.php:handle',
                    'message' => 'database error page',
                    'data' => [
                        'url' => $request->path(),
                        'user_id' => $authUser->user_id ?? null,
                        'role' => $authUser ? (string) $authUser->role : null,
                        'office_id' => $authUser->office_id ?? null,
                        'sqlstate' => $e instanceof QueryException ? ($e->errorInfo[0] ?? null) : $e->getCode(),
                        'error' => substr($driverMessage, 0, 400),
                    ],
                    'timestamp' => (int) round(microtime(true) * 1000),
                ], JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $logException) {
            }
            // #endregion

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
