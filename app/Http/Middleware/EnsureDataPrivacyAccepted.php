<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDataPrivacyAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $accepted = $request->session()->get('nutilize_privacy_accepted') === true;

        // #region agent log
        try {
            file_put_contents(base_path('debug-c2da69.log'), json_encode([
                'sessionId' => 'c2da69',
                'runId' => 'pre-fix',
                'hypothesisId' => 'A',
                'location' => 'EnsureDataPrivacyAccepted.php:handle',
                'message' => 'login privacy gate',
                'data' => [
                    'accepted' => $accepted,
                    'method' => $request->method(),
                    'path' => $request->path(),
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ]).PHP_EOL, FILE_APPEND);
        } catch (\Throwable $throwable) {
        }
        // #endregion

        if ($accepted) {
            return $next($request);
        }

        return redirect()->route('data-privacy');
    }
}
