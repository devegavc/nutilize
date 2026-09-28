<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = bin2hex(random_bytes(16));
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        $this->applyNonceToMarkup($response, $nonce);

        $policy = $this->contentSecurityPolicy($nonce);
        $response->headers->set('Content-Security-Policy', $policy);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Lets public/.htaccess replace a hosting-panel CSP (upgrade-insecure-requests,
        // frame-ancestors *) with this per-request policy when the SAPI exposes env vars.
        if (function_exists('apache_setenv')) {
            apache_setenv('CSP_POLICY', $policy);
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net",
            "style-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "img-src 'self' data:",
            "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-src 'self'",
            "media-src 'self'",
            "object-src 'none'",
            "manifest-src 'self'",
            "worker-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            'upgrade-insecure-requests',
        ];

        return implode('; ', $directives);
    }

    private function applyNonceToMarkup(Response $response, string $nonce): void
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains(strtolower($contentType), 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return;
        }

        if (! str_contains($content, 'name="csp-nonce"')) {
            $meta = '<meta name="csp-nonce" content="'.$nonce.'">';
            $updated = preg_replace('/<head\b[^>]*>/i', '$0'.$meta, $content, 1);
            if (is_string($updated)) {
                $content = $updated;
            }
        }

        $updated = preg_replace_callback(
            '/<(script|style)\b([^>]*?)>/i',
            static function (array $matches) use ($nonce): string {
                if (preg_match('/\bnonce\s*=/i', $matches[2]) === 1) {
                    return $matches[0];
                }

                return '<'.$matches[1].' nonce="'.$nonce.'"'.$matches[2].'>';
            },
            $content
        );

        if (! is_string($updated)) {
            return;
        }

        $response->setContent($updated);

        if ($response->headers->has('ETag')) {
            $response->setEtag(sha1($updated));
        }
    }
}
