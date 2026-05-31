<?php

use App\Http\Controllers\Api\DraftsController;
use App\Http\Controllers\Api\SlackEventsController;
use Illuminate\Support\Facades\Route;

Route::post('/slack/events', SlackEventsController::class)
    ->middleware('slack.signature');

Route::middleware('drafts.token')->prefix('drafts')->group(function (): void {
    Route::get('/tasks', [DraftsController::class, 'show']);
    Route::post('/tasks/add', [DraftsController::class, 'store']);
    Route::post('/tasks', [DraftsController::class, 'update']);
    Route::put('/tasks', [DraftsController::class, 'update']);
});
