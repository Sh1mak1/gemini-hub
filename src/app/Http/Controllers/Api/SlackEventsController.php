<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSlackEventJob;
use App\Support\OperationLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlackEventsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        if (($payload['type'] ?? null) === 'url_verification') {
            OperationLogger::info('slack.event', 'url_verification', []);

            return response()->json([
                'challenge' => $payload['challenge'] ?? '',
            ]);
        }

        OperationLogger::info('slack.event', 'received', [
            'event_type' => $payload['event']['type'] ?? null,
            'channel_id' => $payload['event']['channel'] ?? ($payload['event']['item']['channel'] ?? null),
        ]);

        ProcessSlackEventJob::dispatch($payload);

        return response()->json([], 200);
    }
}
