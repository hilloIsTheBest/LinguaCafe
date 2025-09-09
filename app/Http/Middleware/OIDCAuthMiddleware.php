<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Closure;

class OIDCAuthMiddleware
{
    /**
     * Ensure the user is authenticated via OIDC.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please log in via OIDC.');
        }

        // Optionally check if the user was authenticated via OIDC.
        // This can be done by checking a custom attribute or session flag.
        // For now, we simply rely on Auth::check().

        return $next($request);
    }
}
