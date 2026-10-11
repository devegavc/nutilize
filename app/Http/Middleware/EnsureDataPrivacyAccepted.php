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

        if ($accepted) {
            return $next($request);
        }

        return redirect()->route('data-privacy');
    }
}
