<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AgentDebugLog
{
    public static function write(string $location, string $message, array $data, string $hypothesisId, string $runId = 'pre-fix'): void
    {
        $payload = json_encode([
            'sessionId' => 'e19b10',
            'timestamp' => (int) round(microtime(true) * 1000),
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'hypothesisId' => $hypothesisId,
            'runId' => $runId,
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return;
        }

        $line = $payload."\n";

        foreach ([base_path('debug-e19b10.log'), storage_path('logs/debug-e19b10.log')] as $path) {
            try {
                file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
            } catch (Throwable) {
            }
        }
    }

    public static function sanitize(string $message): string
    {
        $message = preg_replace('/password[=:]\s*\S+/i', 'password=***', $message) ?? $message;
        $message = preg_replace('/user(name)?[=:]\s*\S+/i', 'user=***', $message) ?? $message;

        return substr($message, 0, 400);
    }

    public static function snapshot(?Throwable $exception = null, ?Request $request = null): array
    {
        $data = [
            'driver' => null,
            'connection' => null,
            'cache_store' => null,
            'session_driver' => null,
            'queue_connection' => null,
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'pdo_pgsql' => extension_loaded('pdo_pgsql'),
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'tables' => [],
            'sqlstate' => null,
            'exception' => $exception ? $exception::class : null,
            'error_code' => $exception?->getCode(),
            'method' => $request?->method(),
            'path' => $request?->path(),
        ];

        try {
            $data['connection'] = config('database.default');
            $data['driver'] = config('database.connections.'.config('database.default').'.driver');
            $data['cache_store'] = config('cache.default');
            $data['session_driver'] = config('session.driver');
            $data['queue_connection'] = config('queue.default');
        } catch (Throwable) {
        }

        if ($exception) {
            $message = $exception->getMessage();
            $data['sanitized_message'] = self::sanitize($message);
            $data['sqlstate'] = preg_match('/SQLSTATE\[(\w+)\]/', $message, $match) ? $match[1] : null;
            $data['missing_relation'] = (bool) preg_match('/(doesn\'t exist|does not exist|Undefined table|no such table)/i', $message);
            $data['could_not_find_driver'] = stripos($message, 'could not find driver') !== false;
            $data['access_denied'] = stripos($message, 'access denied') !== false;
            $data['syntax_error'] = (bool) preg_match('/syntax error|SQLSTATE\[42000\]|SQLSTATE\[42601\]/i', $message);
            $data['mentions_cache'] = stripos($message, 'cache') !== false;
            $data['mentions_sessions'] = (bool) preg_match('/\bsessions?\b/i', $message);
            $data['mentions_pgsql'] = stripos($message, 'pgsql') !== false;
        }

        foreach (['cache', 'cache_locks', 'sessions', 'users', 'jobs', 'migrations'] as $table) {
            try {
                $data['tables'][$table] = Schema::hasTable($table);
            } catch (Throwable $throwable) {
                $data['tables'][$table] = 'error';
            }
        }

        return $data;
    }
}
