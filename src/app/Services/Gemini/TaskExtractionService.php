<?php

namespace App\Services\Gemini;

use App\Data\ExtractedTaskData;
use Illuminate\Support\Facades\Log;
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
            $primary = $this->extractPrimary($inputText);
            $review = $this->reviewExtraction($inputText, $primary);

            if (($review['needs_escalation'] ?? false) === true) {
                $final = $this->escalate($inputText, $primary, $review);
            } else {
                $final = $review;
            }

            if (($final['is_valid'] ?? false) !== true) {
                Log::warning('Task extraction rejected after review.', [
                    'issues' => $final['issues'] ?? [],
                ]);

                return null;
            }

            $task = ExtractedTaskData::fromArray($final);

            if ($task->title === '') {
                return null;
            }

            return $task;
        } catch (Throwable $exception) {
            Log::error('Task extraction failed.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPrimary(string $inputText): array
    {
        $prompt = <<<PROMPT
以下のテキストからタスク情報を抽出してください。
推測で情報を作らず、テキストに明示されていない項目は null にしてください。
期日は YYYY-MM-DD 形式で返してください。年が不明な場合は今年として解釈してください。

入力テキスト:
{$inputText}
PROMPT;

        return $this->gemini->generateStructured(
            model: (string) config('gemini.models.flash'),
            prompt: $prompt,
            responseSchema: $this->primarySchema(),
        );
    }

    /**
     * @param  array<string, mixed>  $primary
     * @return array<string, mixed>
     */
    private function reviewExtraction(string $inputText, array $primary): array
    {
        $primaryJson = json_encode($primary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
あなたはタスク抽出結果のレビュアーです。
元テキストと1次抽出結果を比較し、矛盾・ハルシネーション・過剰推測がないか確認してください。
問題があれば修正し、判定不能な場合のみ needs_escalation を true にしてください。

元テキスト:
{$inputText}

1次抽出結果:
{$primaryJson}
PROMPT;

        return $this->gemini->generateStructured(
            model: (string) config('gemini.models.flash'),
            prompt: $prompt,
            responseSchema: $this->reviewSchema(),
        );
    }

    /**
     * @param  array<string, mixed>  $primary
     * @param  array<string, mixed>  $review
     * @return array<string, mixed>
     */
    private function escalate(string $inputText, array $primary, array $review): array
    {
        $primaryJson = json_encode($primary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $reviewJson = json_encode($review, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
2次精査で判定不能とされたタスク抽出を、より慎重に再評価してください。
元テキストに忠実に、確信できる情報のみを返してください。

元テキスト:
{$inputText}

1次抽出結果:
{$primaryJson}

2次精査結果:
{$reviewJson}
PROMPT;

        return $this->gemini->generateStructured(
            model: (string) config('gemini.models.pro'),
            prompt: $prompt,
            responseSchema: $this->reviewSchema(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function primarySchema(): array
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

    /**
     * @return array<string, mixed>
     */
    private function reviewSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'is_valid' => ['type' => 'boolean'],
                'needs_escalation' => ['type' => 'boolean'],
                'title' => ['type' => 'string'],
                'due_date' => ['type' => 'string', 'nullable' => true],
                'category' => [
                    'type' => 'string',
                    'enum' => ['work', 'hobby', 'other'],
                ],
                'location' => ['type' => 'string', 'nullable' => true],
                'issues' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['is_valid', 'needs_escalation', 'title', 'category', 'issues'],
        ];
    }
}
