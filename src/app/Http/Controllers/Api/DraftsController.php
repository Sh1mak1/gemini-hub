<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Services\Drafts\DraftsDiffService;
use App\Services\Drafts\DraftsTaskCache;
use App\Services\Drafts\DraftsTaskCreateService;
use App\Services\Drafts\DraftsTaskListService;
use App\Services\Slack\SlackNotificationService;
use App\Support\OperationLogger;
use App\Support\TodayDueTasksSlackDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DraftsController extends Controller
{
    public function show(DraftsTaskListService $taskListService): Response
    {
        $text = $taskListService->fetchAndCache();

        OperationLogger::info('drafts.fetch', 'success', [
            'line_count' => $text === '' ? 0 : substr_count($text, "\n") + 1,
        ]);

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function store(
        Request $request,
        DraftsTaskCreateService $createService,
        DraftsTaskListService $taskListService,
        SlackNotificationService $slackNotification,
        TodayDueTasksSlackDispatcher $todayDueTasksSlack,
    ): Response {
        $input = $this->extractRequestText($request);

        if ($input === '') {
            return response('Invalid request body.', 400, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $createdIds = $createService->createFromInput($input);

        OperationLogger::info('drafts.add', $createdIds === [] ? 'no_tasks_created' : 'success', [
            'created_ids' => $createdIds,
            'input_preview' => mb_substr($input, 0, 100),
        ]);

        if ($createdIds === []) {
            return response(
                "タスクを追加できませんでした。入力内容を確認してください。\n",
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        foreach ($createdIds as $ref) {
            $task = $this->findCreatedTask($ref);

            if ($task !== null) {
                $slackNotification->notifyTaskCreated($task);
            }
        }

        $text = $taskListService->fetchAndCache();
        $todayDueTasksSlack->dispatchNow();

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
        TodayDueTasksSlackDispatcher $todayDueTasksSlack,
    ): Response {
        $updatedText = $this->extractRequestText($request);

        if ($updatedText === '') {
            return response('Invalid request body.', 400, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $previousSnapshot = $cache->get();

        if ($previousSnapshot === null) {
            OperationLogger::warning('drafts.sync', 'no_snapshot', []);

            return response(
                "前回のタスクリストが見つかりません。先に GET /api/drafts/tasks で一覧を取得してください。\n",
                409,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $completedTaskIds = $diffService->detectCompletedTaskIds($previousSnapshot, $updatedText);
        $markedIds = $diffService->markTasksCompleted($completedTaskIds);

        OperationLogger::info('drafts.sync', 'completed', [
            'detected_ids' => $completedTaskIds,
            'marked_ids' => $markedIds,
        ]);

        $text = $taskListService->fetchAndCache();
        $todayDueTasksSlack->dispatchNow();

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function findCreatedTask(string $ref): Task|FallbackTask|null
    {
        [$source, $id] = array_pad(explode(':', $ref, 2), 2, null);

        if (! is_string($source) || ! is_string($id) || ! ctype_digit($id)) {
            return null;
        }

        return match ($source) {
            'ai' => Task::query()->find((int) $id),
            'fallback' => FallbackTask::query()->find((int) $id),
            default => null,
        };
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
