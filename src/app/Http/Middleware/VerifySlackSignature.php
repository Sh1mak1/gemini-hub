<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySlackSignature
{
    private const int MAX_TIMESTAMP_AGE_SECONDS = 300;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signingSecret = config('services.slack.signing_secret');

        if (empty($signingSecret)) {
            abort(500, 'Slack signing secret is not configured.');
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (! is_string($timestamp) || ! is_string($signature)) {
            abort(401, 'Missing Slack signature headers.');
        }

        if (abs(time() - (int) $timestamp) > self::MAX_TIMESTAMP_AGE_SECONDS) {
            abort(401, 'Slack request timestamp is too old.');
        }

        $body = $request->getContent();
        $baseString = "v0:{$timestamp}:{$body}";
        $computedSignature = 'v0='.hash_hmac('sha256', $baseString, $signingSecret);

        if (! hash_equals($computedSignature, $signature)) {
            abort(401, 'Invalid Slack signature.');
        }

        return $next($request);
    }
}
