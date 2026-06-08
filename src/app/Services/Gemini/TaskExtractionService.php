<?php

namespace App\Services\Gemini;

use App\Data\ExtractedTaskData;
use App\Data\GeminiExtractionFailure;
use App\Data\TaskExtractionOutcome;
use App\Exceptions\GeminiApiException;
use App\Support\OperationLogger;
use Throwable;

class TaskExtractionService
{
    public function __construct(
        private GeminiClient $gemini,
    ) {}

    public function extract(string $inputText): ?TaskExtractionOutcome
    {
        $inputText = trim($inputText);

        if ($inputText === '') {
            return null;
        }

        try {
            $result = $this->gemini->generateStructured(
                model: (string) config('gemini.models.flash'),
                prompt: $this->buildPrompt($inputText),
                responseSchema: $this->responseSchema(),
            );

            $task = ExtractedTaskData::fromArray($result);

            if ($task->title === '') {
                OperationLogger::warning('gemini.extract', 'empty_title_fallback', [
                    'input_preview' => mb_substr($inputText, 0, 100),
                ]);

                return TaskExtractionOutcome::fromFallback($inputText);
            }

            OperationLogger::info('gemini.extract', 'success', [
                'title' => $task->title,
                'category' => $task->category->value,
            ]);

            return TaskExtractionOutcome::fromAi($task);
        } catch (Throwable $exception) {
            $failure = $this->toExtractionFailure($exception);

            OperationLogger::warning('gemini.extract', 'fallback', [
                'input_preview' => mb_substr($inputText, 0, 100),
                'error' => $failure->message,
                'status' => $failure->status,
                'retryable' => $failure->retryable,
            ]);

            return TaskExtractionOutcome::fromFallback($inputText, $failure);
        }
    }

    private function toExtractionFailure(Throwable $exception): GeminiExtractionFailure
    {
        if ($exception instanceof GeminiApiException) {
            return new GeminiExtractionFailure(
                message: $exception->getMessage(),
                status: $exception->status,
                retryable: $exception->retryable,
            );
        }

        return new GeminiExtractionFailure(
            message: $exception->getMessage(),
        );
    }

    private function buildPrompt(string $inputText): string
    {
        $now = now(config('gemini.reference_timezone', 'Asia/Tokyo'));
        $referenceContext = $this->formatReferenceDateTime($now);

        return <<<PROMPT
以下のテキストからタスク情報を抽出してください。
推測で情報を作らず、テキストに明示されていない項目は null にしてください。
期日は YYYY-MM-DD 形式で返してください。

{$referenceContext}

「明日」「来週の金曜」「今月末」などの相対日付は、上記の現在日時を基準に解釈してください。

入力テキスト:
{$inputText}
PROMPT;
    }

    private function formatReferenceDateTime(\Illuminate\Support\Carbon $now): string
    {
        $timezone = $now->timezoneName;
        $weekday = ['日', '月', '火', '水', '木', '金', '土'][$now->dayOfWeek];

        return <<<CONTEXT
現在日時（基準）: {$now->format('Y-m-d H:i')} ({$timezone})
今日の日付: {$now->format('Y-m-d')}
曜日: {$weekday}曜日
CONTEXT;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'due_date' => ['type' => 'string', 'nullable' => true],
                'category' => [
                    'type' => 'string',
                    'enum' => ['work', 'hobby', 'other'],
                ],
                'location' => ['type' => 'string', 'nullable' => true],
            ],
            'required' => ['title', 'category'],
        ];
    }
}
