<?php

namespace App\Services\Operations;

use App\Support\DisplayTime;
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
        $dates = [];

        foreach ($this->logFiles() as $path) {
            foreach ($this->tailLines($path, 2000) as $line) {
                $entry = $this->parseLine($line);

                if ($entry !== null) {
                    $dates[substr($entry['timestamp'], 0, 10)] = true;
                }
            }
        }

        if ($dates === []) {
            return [DisplayTime::today()->toDateString()];
        }

        return collect(array_keys($dates))
            ->sortDesc()
            ->values()
            ->all();
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
        $selectedDate = $date ?: DisplayTime::today()->toDateString();

        return collect($this->collectEntries($limit * 3))
            ->filter(fn (array $entry) => str_starts_with($entry['timestamp'], $selectedDate))
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
    private function collectEntries(int $maxLines): array
    {
        $lines = [];

        foreach ($this->logFiles()->take(3) as $path) {
            $lines = array_merge($lines, $this->tailLines($path, $maxLines));
        }

        return collect($lines)
            ->map(fn (string $line) => $this->parseLine($line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function logFiles(): Collection
    {
        $files = File::glob(storage_path('logs/operations-*.log')) ?: [];

        return collect($files)->sortDesc()->values();
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

        $timestamp = Carbon::parse($matches[1], $this->logTimestampTimezone())
            ->timezone(DisplayTime::timezone())
            ->format('Y-m-d H:i:s');
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

    private function logTimestampTimezone(): string
    {
        return (string) config('logging.operations_log_timezone', 'UTC');
    }
}
