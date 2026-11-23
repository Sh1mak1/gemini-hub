<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwner
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isGuest()) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                abort(403);
            }

            return redirect()->route('debug.contact');
        }

        return $next($request);
    }
};
