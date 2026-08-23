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
            $debugPayload = \App\Support\AgentDebugLog::snapshot($e, $request);
            \App\Support\AgentDebugLog::write('HandleDatabaseErrors.php:handle', 'middleware caught db exception', $debugPayload, 'E');
            // #endregion

            // Log the error
            \Log::error('Database Connection Error', [
                'error' => $e->getMessage(),
                'url' => $request->url(),
            ]);

            // Check if it's a network/connection error
            if ($this->isNetworkError($e)) {
                return response()->view('errors.database-offline', [
                    'message' => 'Database connection unavailable. Please check your network connection.',
                    'debugPayload' => $debugPayload,
                ], 503);
            }

            // Other database errors
            return response()->view('errors.database-error', [
                'message' => 'Database error occurred.',
                'debugPayload' => $debugPayload,
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
