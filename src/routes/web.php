<?php

use App\Http\Controllers\DebugController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TourismTestController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('tasks.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/{source}/{id}', [TaskController::class, 'show'])
        ->whereIn('source', ['ai', 'fallback'])
        ->whereNumber('id')
        ->name('tasks.show');
    Route::patch('/tasks/{source}/{id}', [TaskController::class, 'update'])
        ->whereIn('source', ['ai', 'fallback'])
        ->whereNumber('id')
        ->name('tasks.update');
    Route::patch('/tasks/{source}/{id}/complete', [TaskController::class, 'complete'])
        ->whereIn('source', ['ai', 'fallback'])
        ->whereNumber('id')
        ->name('tasks.complete');

    Route::get('/debug/logs', [DebugController::class, 'logs'])->name('debug.logs');
    Route::get('/debug/logs/entries', [DebugController::class, 'logEntries'])->name('debug.logs.entries');
    Route::get('/debug/database', [DebugController::class, 'database'])->name('debug.database');
    Route::get('/debug/tourism', [TourismTestController::class, 'index'])->name('debug.tourism');
    Route::post('/debug/tourism/search', [TourismTestController::class, 'search'])->name('debug.tourism.search');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
