<?php

namespace App\Services\Slack;

use App\Data\TodayDueTasksSlackPostState;
use App\Models\FallbackTask;
use App\Models\Task;
use App\Support\DisplayTime;
use App\Support\OperationLogger;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TodayDueTasksSlackService
{
    private const string CACHE_KEY_PREFIX = 'slack.today.daily_post.';
    private const int GANTT_MAX_DAYS = 30;

    public function __construct(
        private SlackApiClient $slackApi,
        private SlackChannelResolver $channelResolver,
    ) {}

    public function sync(bool $forceScheduled = false): void
    {
        $today = DisplayTime::today()->format('Y-m-d');
        $now = Carbon::now(DisplayTime::timezone());
        $postHour = (int) config('services.slack.today.post_hour', 9);
        $existing = $this->getPostState($today);

        if (! $forceScheduled && $now->hour < $postHour && $existing === null) {
            return;
        }

        $channelId = $this->channelResolver->resolveTodayChannel();

        if ($channelId === null) {
            OperationLogger::warning('slack.today', 'channel_not_found', [
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
                $this->deletePreviousMessage($channelId, $existing->messageTs);
            }

            $messageTs = $this->slackApi->postMessage($channelId, $payload['text']);
            $this->storePostState($today, $channelId, $messageTs, $payload['hash']);

            OperationLogger::info('slack.today', $existing !== null ? 'reposted' : 'posted', [
                'date' => $today,
                'task_count' => $payload['count'],
                'channel_id' => $channelId,
                'message_ts' => $messageTs,
                'previous_message_ts' => $existing?->messageTs,
                'forced' => $forceScheduled,
            ]);
        } catch (Throwable $exception) {
            OperationLogger::error('slack.today', 'sync_failed', [
                'date' => $today,
                'channel_id' => $channelId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function deletePreviousMessage(string $channelId, string $messageTs): void
    {
        try {
            $this->slackApi->deleteMessage($channelId, $messageTs);
        } catch (Throwable $exception) {
            OperationLogger::warning('slack.today', 'delete_failed', [
                'channel_id' => $channelId,
                'message_ts' => $messageTs,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{text: string, hash: string, count: int}
     */
    private function buildPayload(string $date): array
    {
        $tasks = $this->fetchOpenDueTasks();
        $text = $this->formatMessage($date, $tasks);
        $hash = hash('sha256', $this->serializeTasksForHash($tasks));

        return [
            'text' => $text,
            'hash' => $hash,
            'count' => $tasks->count(),
        ];
    }

    /**
     * @return Collection<int, array{source: string, id: int, title: string, due_date: ?string, days_until_due: int, category: string, location: ?string}>
     */
    private function fetchOpenDueTasks(): Collection
    {
        $today = DisplayTime::today();

        $tasks = Task::query()
            ->pending()
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $task) => $this->normalizeTask($task, 'ai', $today));

        $fallbackTasks = FallbackTask::query()
            ->pending()
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(fn (FallbackTask $task) => $this->normalizeTask($task, 'fallback', $today));

        return $tasks
            ->merge($fallbackTasks)
            ->sortBy(fn (array $task) => sprintf(
                '%s|%s|%010d',
                $task['due_date'] ?? '9999-99-99',
                $task['source'],
                $task['id'],
            ))
            ->values();
    }

    /**
     * @return array{source: string, id: int, title: string, due_date: ?string, days_until_due: int, category: string, location: ?string}
     */
    private function normalizeTask(Task|FallbackTask $task, string $source, CarbonInterface $today): array
    {
        $dueDate = $task->due_date?->copy()->startOfDay();

        return [
            'source' => $source,
            'id' => $task->id,
            'title' => $task->title,
            'due_date' => $dueDate?->format('Y-m-d'),
            'days_until_due' => $dueDate === null ? 0 : (int) $today->diffInDays($dueDate, false),
            'category' => $task->category->label(),
            'location' => $task->location,
        ];
    }

    /**
     * @param  Collection<int, array{source: string, id: int, title: string, due_date: ?string, days_until_due: int, category: string, location: ?string}>  $tasks
     */
    private function serializeTasksForHash(Collection $tasks): string
    {
        return $tasks
            ->map(fn (array $task) => implode('|', [
                $task['source'],
                (string) $task['id'],
                $task['title'],
                $task['due_date'] ?? '',
                (string) $task['days_until_due'],
                $task['category'],
                $task['location'] ?? '',
            ]))
            ->implode("\n");
    }

    /**
     * @param  Collection<int, array{source: string, id: int, title: string, due_date: ?string, days_until_due: int, category: string, location: ?string}>  $tasks
     */
    private function formatMessage(string $date, Collection $tasks): string
    {
        $lines = [
            "📋 期限付き未完了タスク（{$date}時点）",
            '各タスク名の次行に、今日から期日までの横棒を表示します（最大30日、+ はそれ以上）。',
            '',
        ];

        if ($tasks->isEmpty()) {
            $lines[] = '（期限付きの未完了タスクなし）';

            return implode("\n", $lines);
        }

        $lines[] = '凡例: D- = 期限切れ / D+0 = 今日 / D+N = あとN日';
        $lines[] = '';

        foreach ($tasks->values() as $index => $task) {
            $number = $index + 1;
            $location = $task['location'] ?? '未設定';
            $dueStatus = $this->formatDueStatus($task['days_until_due']);
            $dueDate = $task['due_date'] ?? '未設定';
            $delta = $this->formatGanttDelta($task['days_until_due']);
            $bar = $this->formatGanttBar($task['days_until_due']);
            $lines[] = "{$number}. {$dueStatus} {$dueDate}｜{$task['title']}（{$task['category']} / 場所: {$location}）";
            $lines[] = "   `{$delta} {$bar}`";
        }

        $lines[] = '';
        $lines[] = "全{$tasks->count()}件";

        return implode("\n", $lines);
    }

    private function formatGanttDelta(int $daysUntilDue): string
    {
        if ($daysUntilDue < 0) {
            return 'D'.$daysUntilDue;
        }

        return 'D+'.$daysUntilDue;
    }

    private function formatGanttBar(int $daysUntilDue): string
    {
        if ($daysUntilDue < 0) {
            $visibleDays = min(abs($daysUntilDue), self::GANTT_MAX_DAYS);
            $overflow = abs($daysUntilDue) > self::GANTT_MAX_DAYS ? '+' : '';

            return '<'.str_repeat('-', $visibleDays).'!'.$overflow;
        }

        $visibleDays = min($daysUntilDue, self::GANTT_MAX_DAYS);
        $overflow = $daysUntilDue > self::GANTT_MAX_DAYS ? '+' : '';

        return '|'.str_repeat('-', $visibleDays).'*'.$overflow;
    }

    private function formatDueStatus(int $daysUntilDue): string
    {
        if ($daysUntilDue < 0) {
            return '🔴 期限切れ（'.abs($daysUntilDue).'日遅れ）';
        }

        if ($daysUntilDue === 0) {
            return '🟠 今日';
        }

        if ($daysUntilDue === 1) {
            return '🟡 明日';
        }

        if ($daysUntilDue <= 3) {
            return "🟡 あと{$daysUntilDue}日";
        }

        if ($daysUntilDue <= 7) {
            return "🔵 あと{$daysUntilDue}日";
        }

        return "🟢 あと{$daysUntilDue}日";
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
