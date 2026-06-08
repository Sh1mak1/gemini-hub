<?php

namespace App\Services\Slack;

use App\Data\TodayDueTasksSlackPostState;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Support\DisplayTime;
use App\Support\OperationLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TodayDueTasksSlackService
{
    private const string CACHE_KEY_PREFIX = 'slack.kyou.daily_post.';

    public function __construct(
        private SlackApiClient $slackApi,
        private SlackChannelResolver $channelResolver,
    ) {}

    public function sync(bool $forceScheduled = false): void
    {
        $today = DisplayTime::today()->format('Y-m-d');
        $now = Carbon::now(DisplayTime::timezone());
        $postHour = (int) config('services.slack.kyou.post_hour', 9);
        $existing = $this->getPostState($today);

        if (! $forceScheduled && $now->hour < $postHour && $existing === null) {
            return;
        }

        $channelId = $this->channelResolver->resolveKyouChannel();

        if ($channelId === null) {
            OperationLogger::warning('slack.kyou', 'channel_not_found', [
                'date' => $today,
            ]);

            return;
        }

        $payload = $this->buildPayload($today);

        if ($existing !== null && $existing->contentHash === $payload['hash']) {
            return;
        }

        try {
            if ($existing !== null) {
                $this->slackApi->updateMessage($channelId, $existing->messageTs, $payload['text']);
                $this->storePostState($today, $channelId, $existing->messageTs, $payload['hash']);

                OperationLogger::info('slack.kyou', 'updated', [
                    'date' => $today,
                    'task_count' => $payload['count'],
                    'channel_id' => $channelId,
                    'message_ts' => $existing->messageTs,
                ]);

                return;
            }

            $messageTs = $this->slackApi->postMessage($channelId, $payload['text']);
            $this->storePostState($today, $channelId, $messageTs, $payload['hash']);

            OperationLogger::info('slack.kyou', 'posted', [
                'date' => $today,
                'task_count' => $payload['count'],
                'channel_id' => $channelId,
                'message_ts' => $messageTs,
                'forced' => $forceScheduled,
            ]);
        } catch (Throwable $exception) {
            OperationLogger::error('slack.kyou', 'sync_failed', [
                'date' => $today,
                'channel_id' => $channelId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array{text: string, hash: string, count: int}
     */
    private function buildPayload(string $date): array
    {
        $tasks = $this->fetchTodayDueTasks();
        $text = $this->formatMessage($date, $tasks);
        $hash = hash('sha256', $this->serializeTasksForHash($tasks));

        return [
            'text' => $text,
            'hash' => $hash,
            'count' => $tasks->count(),
        ];
    }

    /**
     * @return Collection<int, array{source: string, id: int, title: string, due_date: ?string, category: string, location: ?string}>
     */
    private function fetchTodayDueTasks(): Collection
    {
        $today = DisplayTime::today();

        $tasks = Task::query()
            ->pending()
            ->whereDate('due_date', $today)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $task) => $this->normalizeTask($task, 'ai'));

        $fallbackTasks = FallbackTask::query()
            ->pending()
            ->whereDate('due_date', $today)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(fn (FallbackTask $task) => $this->normalizeTask($task, 'fallback'));

        return $tasks->merge($fallbackTasks)->values();
    }

    /**
     * @return array{source: string, id: int, title: string, due_date: ?string, category: string, location: ?string}
     */
    private function normalizeTask(Task|FallbackTask $task, string $source): array
    {
        return [
            'source' => $source,
            'id' => $task->id,
            'title' => $task->title,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'category' => $task->category->label(),
            'location' => $task->location,
        ];
    }

    /**
     * @param  Collection<int, array{source: string, id: int, title: string, due_date: ?string, category: string, location: ?string}>  $tasks
     */
    private function serializeTasksForHash(Collection $tasks): string
    {
        return $tasks
            ->map(fn (array $task) => implode('|', [
                $task['source'],
                (string) $task['id'],
                $task['title'],
                $task['due_date'] ?? '',
                $task['category'],
                $task['location'] ?? '',
            ]))
            ->implode("\n");
    }

    /**
     * @param  Collection<int, array{source: string, id: int, title: string, due_date: ?string, category: string, location: ?string}>  $tasks
     */
    private function formatMessage(string $date, Collection $tasks): string
    {
        $lines = ["📋 本日（{$date}）が期限のタスク", ''];

        if ($tasks->isEmpty()) {
            $lines[] = '（なし）';

            return implode("\n", $lines);
        }

        foreach ($tasks->values() as $index => $task) {
            $number = $index + 1;
            $location = $task['location'] ?? '未設定';
            $lines[] = "{$number}. {$task['title']}（{$task['category']} / 場所: {$location}）";
        }

        $lines[] = '';
        $lines[] = "全{$tasks->count()}件";

        return implode("\n", $lines);
    }

    private function getPostState(string $date): ?TodayDueTasksSlackPostState
    {
        $cached = Cache::get(self::CACHE_KEY_PREFIX.$date);

        if (! is_array($cached)) {
            return null;
        }

        return TodayDueTasksSlackPostState::fromArray($cached);
    }

    private function storePostState(string $date, string $channelId, string $messageTs, string $contentHash): void
    {
        $state = new TodayDueTasksSlackPostState($channelId, $messageTs, $contentHash);

        Cache::put(
            self::CACHE_KEY_PREFIX.$date,
            $state->toArray(),
            DisplayTime::today()->endOfDay()->timezone(DisplayTime::timezone()),
        );
    }
}
