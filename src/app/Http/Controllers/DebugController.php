<?php

namespace App\Http\Controllers;

use App\Services\Database\DatabaseTableBrowser;
use App\Services\Operations\OperationLogReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DebugController extends Controller
{
    public function logs(Request $request, OperationLogReader $reader): Response
    {
        $date = $request->string('date')->toString() ?: null;
        $operation = $request->string('operation')->toString() ?: null;
        $level = $request->string('level')->toString() ?: null;
        $availableDates = $reader->availableDates();
        $selectedDate = $date ?: ($availableDates[0] ?? today()->toDateString());

        return Inertia::render('Debug/Logs', [
            'entries' => $reader->read($selectedDate, operation: $operation, level: $level),
            'availableDates' => $availableDates,
            'operations' => $reader->knownOperations($selectedDate),
            'filters' => [
                'date' => $selectedDate,
                'operation' => $operation ?? '',
                'level' => $level ?? '',
            ],
        ]);
    }

    public function logEntries(Request $request, OperationLogReader $reader): JsonResponse
    {
        $date = $request->string('date')->toString() ?: null;
        $operation = $request->string('operation')->toString() ?: null;
        $level = $request->string('level')->toString() ?: null;
        $availableDates = $reader->availableDates();
        $selectedDate = $date ?: ($availableDates[0] ?? today()->toDateString());

        return response()->json([
            'entries' => $reader->read($selectedDate, operation: $operation, level: $level),
            'operations' => $reader->knownOperations($selectedDate),
            'filters' => [
                'date' => $selectedDate,
                'operation' => $operation ?? '',
                'level' => $level ?? '',
            ],
        ]);
    }

    public function database(Request $request, DatabaseTableBrowser $browser): Response
    {
        $tables = $browser->tables();
        $table = $request->string('table')->toString() ?: ($tables[0] ?? 'tasks');
        $page = max(1, $request->integer('page', 1));

        if (! in_array($table, $tables, true)) {
            $table = $tables[0] ?? 'tasks';
        }

        return Inertia::render('Debug/Database', [
            'tables' => $tables,
            'selectedTable' => $table,
            'tableData' => $browser->browse($table, $page),
        ]);
    }
}
