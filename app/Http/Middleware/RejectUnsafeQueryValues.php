<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectUnsafeQueryValues
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query->has('v') && !$this->isAllowedCacheBuster($request->query('v'))) {
            // #region agent log
            $this->agentLog('A', 'rejected cache-buster', ['param' => 'v', 'value' => $this->preview($request->query('v'))]);
            // #endregion
            return $this->reject();
        }

        if ($request->query->has('force') && !$this->isAllowedForceFlag($request->query('force'))) {
            // #region agent log
            $this->agentLog('C', 'rejected force flag', ['param' => 'force', 'value' => $this->preview($request->query('force'))]);
            // #endregion
            return $this->reject();
        }

        if ($request->query->has('v') || $request->query->has('force')) {
            // #region agent log
            $this->agentLog('A', 'allowed query flags', [
                'v' => $request->query->has('v') ? $this->preview($request->query('v')) : null,
                'force' => $request->query->has('force') ? $this->preview($request->query('force')) : null,
            ]);
            // #endregion
        }

        return $next($request);
    }

    private function isAllowedCacheBuster(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,80}$/', $value) === 1;
    }

    private function isAllowedForceFlag(mixed $value): bool
    {
        return is_string($value) && preg_match('/^(?:0|1|true|false|on|off|yes|no)$/i', $value) === 1;
    }

    private function reject(): Response
    {
        return response('Bad Request', 400)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'no-store');
    }

    private function preview(mixed $value): string
    {
        if (!is_string($value)) {
            return get_debug_type($value);
        }

        $clean = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';

        return substr($clean, 0, 80);
    }

    private function agentLog(string $hypothesisId, string $message, array $data): void
    {
        // #region agent log
        $line = json_encode([
            'sessionId' => '10fa97',
            'hypothesisId' => $hypothesisId,
            'location' => 'RejectUnsafeQueryValues.php',
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
            'runId' => 'post-fix',
        ], JSON_UNESCAPED_SLASHES);
        @file_put_contents(base_path('debug-10fa97.log'), $line.PHP_EOL, FILE_APPEND);
        // #endregion
    }
}
