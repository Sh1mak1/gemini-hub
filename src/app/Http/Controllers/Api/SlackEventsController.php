<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSlackEventJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlackEventsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        if (($payload['type'] ?? null) === 'url_verification') {
            return response()->json([
                'challenge' => $payload['challenge'] ?? '',
            ]);
        }

        ProcessSlackEventJob::dispatch($payload);

        return response()->json([], 200);
    }
}
