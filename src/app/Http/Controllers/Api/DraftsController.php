<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Drafts\DraftsDiffService;
use App\Services\Drafts\DraftsTaskCache;
use App\Services\Drafts\DraftsTaskCreateService;
use App\Services\Drafts\DraftsTaskListService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DraftsController extends Controller
{
    public function show(DraftsTaskListService $taskListService): Response
    {
        $text = $taskListService->fetchAndCache();

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function store(
        Request $request,
        DraftsTaskCreateService $createService,
        DraftsTaskListService $taskListService,
    ): Response {
        $input = $this->extractRequestText($request);

        if ($input === '') {
            return response('Invalid request body.', 400, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $createdIds = $createService->createFromInput($input);

        if ($createdIds === []) {
            return response(
                "タスクを追加できませんでした。入力内容を確認してください。\n",
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $text = $taskListService->fetchAndCache();

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Tasks-Created' => implode(',', $createdIds),
        ]);
    }

    public function update(
        Request $request,
        DraftsTaskCache $cache,
        DraftsDiffService $diffService,
        DraftsTaskListService $taskListService,
    ): Response {
        $updatedText = $this->extractRequestText($request);

        if ($updatedText === '') {
            return response('Invalid request body.', 400, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $previousSnapshot = $cache->get();

        if ($previousSnapshot === null) {
            return response(
                "前回のタスクリストが見つかりません。先に GET /api/drafts/tasks で一覧を取得してください。\n",
                409,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $completedTaskIds = $diffService->detectCompletedTaskIds($previousSnapshot, $updatedText);
        $diffService->markTasksCompleted($completedTaskIds);

        $text = $taskListService->fetchAndCache();

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function extractRequestText(Request $request): string
    {
        $rawBody = $request->getContent();

        if (is_string($rawBody) && trim($rawBody) !== '') {
            $contentType = $request->header('Content-Type', '');

            if (! is_string($contentType) || ! str_contains($contentType, 'application/json')) {
                return trim($rawBody);
            }

            $decoded = json_decode($rawBody, true);

            if (is_array($decoded)) {
                foreach (['text', 'content'] as $key) {
                    $value = $decoded[$key] ?? null;

                    if (is_string($value) && trim($value) !== '') {
                        return trim($value);
                    }
                }
            }
        }

        foreach (['text', 'content'] as $key) {
            $value = $request->input($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}
