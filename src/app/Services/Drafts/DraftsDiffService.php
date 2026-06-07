<?php

namespace App\Services\Drafts;

use App\Data\DraftsTaskSnapshot;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\Gemini\GeminiClient;
use App\Support\OperationLogger;
use Throwable;

class DraftsDiffService
{
    public function __construct(
        private GeminiClient $gemini,
    ) {}

    /**
     * @return list<int>
     */
    public function detectCompletedTaskIds(DraftsTaskSnapshot $previous, string $updatedText): array
    {
        $updatedText = trim($updatedText);

        if ($updatedText === '' || $updatedText === $previous->text) {
            return [];
        }

        try {
            $result = $this->gemini->generateStructured(
                model: (string) config('gemini.models.flash'),
                prompt: $this->buildPrompt($previous, $updatedText),
                responseSchema: $this->responseSchema(),
            );

            $lineNumbers = $result['completed_line_numbers'] ?? [];

            if (! is_array($lineNumbers)) {
                return [];
            }

            $normalizedLineNumbers = array_values(array_filter(
                array_map(static fn ($line) => is_int($line) ? $line : null, $lineNumbers),
                static fn ($line) => $line !== null,
            ));

            return $previous->taskIdsForLines($normalizedLineNumbers);
        } catch (Throwable $exception) {
            OperationLogger::error('drafts.sync', 'diff_failed', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<int>
     */
    public function markTasksCompleted(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }

        $completedIds = [];

        Task::query()
            ->whereIn('id', $taskIds)
            ->where('status', TaskStatus::Pending)
            ->each(function (Task $task) use (&$completedIds): void {
                if ($task->markCompleted()) {
                    $completedIds[] = $task->id;
                }
            });

        return $completedIds;
    }

    private function buildPrompt(DraftsTaskSnapshot $previous, string $updatedText): string
    {
        $lineMap = collect($previous->lines)
            ->map(fn (array $line, int $number) => "{$number}: [id={$line['id']}] {$line['title']}")
            ->implode("\n");

        return <<<PROMPT
あなたはToDoリストの差分解析アシスタントです。
「前回送った元テキスト」と「ユーザーが編集した新テキスト」を比較し、完了になった行を特定してください。

完了の判定例:
- 行が削除された
- 「完了」「済」「done」などが追記された
- チェックボックスがオンになった（例: [x]）
- 取り消し線が付いた

行番号は前回テキストの番号（1始まり）を使ってください。
確信が持てない行は completed_line_numbers に含めないでください。

行番号とDB IDの対応:
{$lineMap}

前回テキスト:
{$previous->text}

編集後テキスト:
{$updatedText}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'completed_line_numbers' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
                'reasoning' => ['type' => 'string'],
            ],
            'required' => ['completed_line_numbers', 'reasoning'],
        ];
    }
}
