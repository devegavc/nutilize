<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectUnsafeQueryValues
{
    /**
     * Version tokens used as ?v= on stylesheets and scripts.
     * Arithmetic such as 9-2 or 1790356908-2 is rejected so an ignored query
     * string cannot look like an evaluated SQL expression.
     */
    private const CACHE_BUSTER = '/^(?:[0-9]{1,12}|login-[0-9]{1,4}|[0-9]{8,12}-[A-Za-z][A-Za-z0-9_-]{0,48})$/';

    private const LOGIN_IDENTIFIER = '/^[A-Za-z0-9._@+\-]{1,50}$/';

    private const SQL_BOOLEAN = '/\b(?:and|or)\b\s+[\'"]?[0-9]+[\'"]?\s*=\s*[\'"]?[0-9]+/i';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query->has('v') && !$this->isAllowedCacheBuster($request->query('v'))) {
            // #region agent log
            $this->agentLog('B', 'rejected cache-buster');
            // #endregion
            return $this->reject();
        }

        if ($request->query->has('force') && !$this->isAllowedForceFlag($request->query('force'))) {
            // #region agent log
            $this->agentLog('D', 'rejected force flag');
            // #endregion
            return $this->reject();
        }

        if ($tokenResponse = $this->rejectMalformedCsrfToken($request)) {
            return $tokenResponse;
        }

        if ($loginResponse = $this->rejectSqlShapedLogin($request)) {
            return $loginResponse;
        }

        return $next($request);
    }

    private function isAllowedCacheBuster(mixed $value): bool
    {
        return is_string($value) && preg_match(self::CACHE_BUSTER, $value) === 1;
    }

    private function isAllowedForceFlag(mixed $value): bool
    {
        return is_string($value) && preg_match('/^(?:0|1|true|false|on|off|yes|no)$/i', $value) === 1;
    }

    private function rejectMalformedCsrfToken(Request $request): ?Response
    {
        if (!$request->request->has('_token') && !$request->query->has('_token')) {
            return null;
        }

        $token = $request->request->has('_token')
            ? $request->request->get('_token')
            : $request->query->get('_token');

        if (!is_string($token) || preg_match('/^[A-Za-z0-9]{1,255}$/', $token) !== 1) {
            // #region agent log
            $this->agentLog('E', 'rejected csrf token shape');
            // #endregion
            return $this->reject();
        }

        return null;
    }

    private function rejectSqlShapedLogin(Request $request): ?Response
    {
        if (!$request->isMethod('POST') || !$request->is('login')) {
            return null;
        }

        $username = $request->request->get('username');
        $password = $request->request->get('password');

        if (!is_string($username) || ($username !== '' && preg_match(self::LOGIN_IDENTIFIER, $username) !== 1)) {
            // #region agent log
            $this->agentLog('C', 'rejected login username');
            // #endregion
            return $this->reject();
        }

        if (!is_string($password) || strlen($password) > 255 || $this->containsSqlBoolean($password)) {
            // #region agent log
            $this->agentLog('C', 'rejected login password');
            // #endregion
            return $this->reject();
        }

        return null;
    }

    private function containsSqlBoolean(string $value): bool
    {
        return preg_match(self::SQL_BOOLEAN, $value) === 1;
    }

    private function agentLog(string $hypothesisId, string $message): void
    {
        // #region agent log
        @file_put_contents(base_path('debug-a9d1a3.log'), json_encode(['sessionId'=>'a9d1a3','hypothesisId'=>$hypothesisId,'location'=>'RejectUnsafeQueryValues.php','message'=>$message,'data'=>['branch'=>$message],'timestamp'=>(int) round(microtime(true)*1000),'runId'=>'recheck'])."\n", FILE_APPEND);
        // #endregion
    }

    private function reject(): Response
    {
        return response('Bad Request', 400)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'no-store');
    }
}
