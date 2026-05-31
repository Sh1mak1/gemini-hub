<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDraftsToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = config('drafts.api_token');

        if (empty($configuredToken)) {
            abort(500, 'Drafts API token is not configured.');
        }

        $providedToken = $this->extractToken($request);

        if ($providedToken === null || ! hash_equals($configuredToken, $providedToken)) {
            abort(401, 'Invalid Drafts API token.');
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $authorization = $request->header('Authorization');

        if (is_string($authorization) && str_starts_with($authorization, 'Bearer ')) {
            $token = trim(substr($authorization, 7));

            return $token !== '' ? $token : null;
        }

        $headerToken = $request->header('X-Drafts-Token');

        if (is_string($headerToken) && $headerToken !== '') {
            return $headerToken;
        }

        $queryToken = $request->query('token');

        return is_string($queryToken) && $queryToken !== '' ? $queryToken : null;
    }
}
