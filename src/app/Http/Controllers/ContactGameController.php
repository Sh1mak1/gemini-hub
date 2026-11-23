<?php

namespace App\Http\Controllers;

use App\Exceptions\GeminiApiException;
use App\Models\ContactGame;
use App\Services\ContactGame\ContactGameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ContactGameController extends Controller
{
    public function index(Request $request): Response
    {
        $game = ContactGame::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        return Inertia::render('Debug/Contact', [
            'game' => $game?->toPublicArray(),
        ]);
    }

    public function start(Request $request, ContactGameService $service): JsonResponse
    {
        $validated = $request->validate([
            'sentence' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        try {
            $game = $service->start($request->user(), $validated['sentence']);

            return response()->json([
                'game' => $game->toPublicArray(),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function message(Request $request, ContactGame $contactGame, ContactGameService $service): JsonResponse
    {
        $game = $this->ownGame($request, $contactGame);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        if ($game->status !== 'ongoing') {
            return response()->json([
                'game' => $game->toPublicArray(),
                'error' => 'この連絡はもう終わっています。',
            ], 422);
        }

        try {
            $game = $service->send($game, $validated['message']);

            return response()->json([
                'game' => $game->toPublicArray(),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception, $game->fresh());
        }
    }

    private function ownGame(Request $request, ContactGame $game): ContactGame
    {
        if ($game->user_id !== $request->user()->id) {
            abort(404);
        }

        return $game;
    }

    private function failure(Throwable $exception, ?ContactGame $game = null): JsonResponse
    {
        return response()->json([
            'game' => $game?->toPublicArray(),
            'error' => $this->errorMessage($exception),
        ], 422);
    }

    private function errorMessage(Throwable $exception): string
    {
        if ($exception instanceof GeminiApiException) {
            return 'Gemini との通信に失敗しました。しばらくしてからもう一度送ってください。';
        }

        if ($exception instanceof RuntimeException) {
            return $exception->getMessage();
        }

        return '処理に失敗しました。';
    }
}
