<?php

namespace App\Http\Middleware;
use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response{
    if (!Auth::check()) {
        return redirect('/login');
    }

    if (!in_array(Auth::user()->role, $roles)) {
        abort(403);
    }

    return $next($request);
}
}
