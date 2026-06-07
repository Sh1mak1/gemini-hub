<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseTableBrowser
{
    private const HIDDEN_COLUMNS = ['password', 'remember_token', 'token'];

    private const MAX_CELL_LENGTH = 200;

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        return collect(Schema::getTableListing())
            ->map(fn (string $table) => $this->shortTableName($table))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     columns: list<string>,
     *     rows: list<array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function browse(string $table, int $page = 1, int $perPage = 50): array
    {
        $this->assertTableExists($table);

        $columns = Schema::getColumnListing($table);
        $orderColumn = in_array('id', $columns, true) ? 'id' : $columns[0];
        $page = max(1, $page);
        $perPage = min(max($perPage, 1), 100);

        $query = DB::table($table);
        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc($orderColumn)
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($row) => $this->formatRow((array) $row, $columns))
            ->all();

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'columns' => $columns,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    private function assertTableExists(string $table): void
    {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
            abort(404);
        }

        if (! in_array($table, $this->tables(), true)) {
            abort(404);
        }
    }

    private function shortTableName(string $table): string
    {
        if (! str_contains($table, '.')) {
            return $table;
        }

        return substr($table, strrpos($table, '.') + 1);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function formatRow(array $row, array $columns): array
    {
        $formatted = [];

        foreach ($columns as $column) {
            $value = $row[$column] ?? null;

            if (in_array($column, self::HIDDEN_COLUMNS, true)) {
                $formatted[$column] = $value === null ? null : '***';

                continue;
            }

            if (is_string($value) && mb_strlen($value) > self::MAX_CELL_LENGTH) {
                $formatted[$column] = mb_substr($value, 0, self::MAX_CELL_LENGTH).'…';

                continue;
            }

            $formatted[$column] = $value;
        }

        return $formatted;
    }
}
