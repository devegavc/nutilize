<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhysicalFacilitiesAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (method_exists($user, 'isPhysicalFacilitiesAdmin') && !$user->isPhysicalFacilitiesAdmin()) {
            return redirect()->route('office.home')->with('error', 'Unauthorized access.');
        }

        return $next($request);
    }
}
