<?php

namespace App\Services\Operations;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class OperationLogReader
{
    /**
     * @return list<string>
     */
    public function availableDates(): array
    {
        $files = File::glob(storage_path('logs/operations-*.log')) ?: [];

        $dates = collect($files)
            ->map(function (string $path): ?string {
                if (preg_match('/operations-(\d{4}-\d{2}-\d{2})\.log$/', $path, $matches) !== 1) {
                    return null;
                }

                return $matches[1];
            })
            ->filter()
            ->sortDesc()
            ->values()
            ->all();

        if ($dates === [] && File::exists(storage_path('logs/operations.log'))) {
            return [today()->toDateString()];
        }

        return $dates;
    }

    /**
     * @return list<array{
     *     timestamp: string,
     *     level: string,
     *     operation: string|null,
     *     step: string|null,
     *     message: string,
     *     context: array<string, mixed>
     * }>
     */
    public function read(
        ?string $date = null,
        int $limit = 200,
        ?string $operation = null,
        ?string $level = null,
    ): array {
        $path = $this->resolveLogPath($date);
        $lines = $this->tailLines($path, $limit * 3);

        return collect($lines)
            ->map(fn (string $line) => $this->parseLine($line))
            ->filter()
            ->when($operation !== null && $operation !== '', fn (Collection $entries) => $entries->filter(
                fn (array $entry) => $entry['operation'] === $operation,
            ))
            ->when($level !== null && $level !== '', fn (Collection $entries) => $entries->filter(
                fn (array $entry) => strtolower($entry['level']) === strtolower($level),
            ))
            ->take(-$limit)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function knownOperations(?string $date = null): array
    {
        return collect($this->read($date, limit: 1000))
            ->pluck('operation')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function resolveLogPath(?string $date): string
    {
        $date = $date ?: today()->toDateString();

        $datedPath = storage_path("logs/operations-{$date}.log");

        if (File::exists($datedPath)) {
            return $datedPath;
        }

        return storage_path('logs/operations.log');
    }

    /**
     * @return list<string>
     */
    private function tailLines(string $path, int $maxLines): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $maxLines + 1);

        $lines = [];

        for ($lineNumber = $start; $lineNumber <= $lastLine; $lineNumber++) {
            $file->seek($lineNumber);
            $line = trim((string) $file->current());

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return array{
     *     timestamp: string,
     *     level: string,
     *     operation: string|null,
     *     step: string|null,
     *     message: string,
     *     context: array<string, mixed>
     * }|null
     */
    private function parseLine(string $line): ?array
    {
        if (preg_match('/^\[([^\]]+)\] [^.]+\.(\w+): (.+)$/', $line, $matches) !== 1) {
            return null;
        }

        $timestamp = Carbon::parse($matches[1])->toDateTimeString();
        $level = strtolower($matches[2]);
        $payload = $matches[3];
        $context = [];

        if (preg_match('/^(.+?) (\{.*\})$/', $payload, $parts) === 1) {
            $payload = $parts[1];
            $decoded = json_decode($parts[2], true);
            $context = is_array($decoded) ? $decoded : [];
        }

        $operation = $context['operation'] ?? null;
        $step = $context['step'] ?? null;

        if (! is_string($operation) && preg_match('/^\[([^\]]+)\] (.+)$/', $payload, $operationParts) === 1) {
            $operation = $operationParts[1];
            $step = $operationParts[2];
        }

        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'operation' => is_string($operation) ? $operation : null,
            'step' => is_string($step) ? $step : null,
            'message' => $payload,
            'context' => $context,
        ];
    }
}
