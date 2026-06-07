<?php

namespace App\Services\Gemini;

use App\Data\ExtractedTaskData;
use App\Support\OperationLogger;
use Throwable;

class TaskExtractionService
{
    public function __construct(
        private GeminiClient $gemini,
    ) {}

    public function extract(string $inputText): ?ExtractedTaskData
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
                OperationLogger::warning('gemini.extract', 'empty_title', [
                    'input_preview' => mb_substr($inputText, 0, 100),
                ]);

                return null;
            }

            OperationLogger::info('gemini.extract', 'success', [
                'title' => $task->title,
                'category' => $task->category->value,
            ]);

            return $task;
        } catch (Throwable $exception) {
            OperationLogger::error('gemini.extract', 'failed', [
                'input_preview' => mb_substr($inputText, 0, 100),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function buildPrompt(string $inputText): string
    {
        return <<<PROMPT
以下のテキストからタスク情報を抽出してください。
推測で情報を作らず、テキストに明示されていない項目は null にしてください。
期日は YYYY-MM-DD 形式で返してください。年が不明な場合は今年として解釈してください。

入力テキスト:
{$inputText}
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
