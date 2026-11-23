<?php

namespace App\Http\Controllers;

use App\Exceptions\GeminiApiException;
use App\Models\AppSetting;
use App\Models\ContactGame;
use App\Services\ContactGame\ContactGameService;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            'llmProvider' => $request->user()->isGuest() ? null : app(ContactGameService::class)->llmProvider(),
        ]);
    }

    public function start(Request $request, ContactGameService $service): JsonResponse
    {
        $validated = $this->validatedJson($request, [
            'sentence' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        try {
            $game = $service->start($request->user(), $validated['sentence']);

            return response()->json($this->payload($game, null, $request));
        } catch (Throwable $exception) {
            return $this->failure($exception, null, $request);
        }
    }

    public function message(Request $request, ContactGame $contactGame, ContactGameService $service): JsonResponse
    {
        $game = $this->ownGame($request, $contactGame);

        $validated = $this->validatedJson($request, [
            'message' => ['required', 'string', 'min:1', 'max:1000'],
        ], $game);

        if ($game->status !== 'ongoing') {
            return response()->json($this->payload($game, 'この連絡はもう終わっています。', $request), 422);
        }

        try {
            $game = $service->send($game, $validated['message']);

            return response()->json($this->payload($game, null, $request));
        } catch (Throwable $exception) {
            return $this->failure($exception, $game->fresh(), $request);
        }
    }

    public function updateProvider(Request $request, ContactGameService $service): JsonResponse
    {
        $validated = $this->validatedJson($request, [
            'provider' => ['required', 'in:gemini,openrouter'],
        ]);

        $apiKey = config('openrouter.api_key');

        if ($validated['provider'] === 'openrouter' && (! is_string($apiKey) || $apiKey === '')) {
            return response()->json([
                'provider' => $service->llmProvider(),
                'error' => 'OpenRouter のキーが設定されていません。',
            ], 422);
        }

        AppSetting::putValue(AppSetting::CONTACT_LLM_PROVIDER, $validated['provider']);

        return response()->json([
            'provider' => $validated['provider'],
            'error' => null,
        ]);
    }

    public function debug(Request $request, ContactGame $contactGame): JsonResponse
    {
        $game = $this->ownGame($request, $contactGame);

        return response()->json([
            'debug' => $game->toDebugArray(),
        ]);
    }

    public function updateResource(Request $request, ContactGame $contactGame): JsonResponse
    {
        $game = $this->ownGame($request, $contactGame);

        $validated = $this->validatedJson($request, [
            'resource_remaining' => ['required', 'integer', 'min:0', 'max:99'],
        ], $game);

        $game->setDebugResource((int) $validated['resource_remaining']);
        $game = $game->fresh() ?? $game;

        return response()->json($this->payload($game, null, $request));
    }

    public function destroy(Request $request, ContactGame $contactGame): JsonResponse
    {
        $game = $this->ownGame($request, $contactGame);
        $game->delete();

        return response()->json([
            'game' => null,
            'error' => null,
            'retryable' => false,
        ]);
    }

    private function ownGame(Request $request, ContactGame $game): ContactGame
    {
        if ($game->user_id !== $request->user()->id) {
            abort(404);
        }

        return $game;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validatedJson(Request $request, array $rules, ?ContactGame $game = null): array
    {
        $validator = validator($request->all(), $rules);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(
                $this->payload($game, $validator->errors()->first(), $request),
                422,
            ));
        }

        return $validator->validated();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(?ContactGame $game, ?string $error, ?Request $request): array
    {
        $body = [
            'game' => $game?->toPublicArray(),
            'error' => $error,
            'retryable' => false,
        ];

        if ($game && $request?->header('X-Contact-Debug') === '1') {
            $body['debug'] = $game->toDebugArray();
        }

        return $body;
    }

    private function failure(Throwable $exception, ?ContactGame $game = null, ?Request $request = null): JsonResponse
    {
        $body = $this->payload($game, $this->errorMessage($exception), $request);
        $body['retryable'] = $exception instanceof GeminiApiException
            || ($exception instanceof RuntimeException && str_contains($exception->getMessage(), '生成に失敗'));

        return response()->json($body, 422);
    }

    private function errorMessage(Throwable $exception): string
    {
        if ($exception instanceof GeminiApiException) {
            return '返事を受け取れませんでした。しばらくしてからもう一度送ってください。';
        }

        if ($exception instanceof RuntimeException) {
            return $exception->getMessage();
        }

        return '処理に失敗しました。';
    }
}
