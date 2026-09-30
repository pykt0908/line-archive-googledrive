<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\LineWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LINE File Archive API Routes
|--------------------------------------------------------------------------
*/

// Public Authentication
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// LINE Webhook Endpoint (Silent Archive)
Route::post('/line/webhook', [LineWebhookController::class, 'handle']);

// Authenticated Teacher Routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth profile
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // File Archive
    Route::prefix('files')->group(function () {
        Route::get('/', [FileController::class, 'index']);
        Route::post('/', [FileController::class, 'store']);
        Route::get('/groups', [FileController::class, 'groups']);
        Route::get('/{id}', [FileController::class, 'show']);
        Route::put('/{id}', [FileController::class, 'update']);
        Route::delete('/{id}', [FileController::class, 'destroy']);
        Route::get('/{id}/download', [FileController::class, 'download']);
        Route::get('/{id}/preview', [FileController::class, 'preview']);
    });

    // Admin Management Routes
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Teacher Management
        Route::get('/teachers', [AdminController::class, 'teachers']);
        Route::post('/teachers', [AdminController::class, 'storeTeacher']);
        Route::put('/teachers/{id}', [AdminController::class, 'updateTeacher']);
        Route::delete('/teachers/{id}', [AdminController::class, 'deleteTeacher']);
        Route::post('/teachers/{id}/toggle-status', [AdminController::class, 'toggleTeacherStatus']);

        // Group Management
        Route::get('/groups', [AdminController::class, 'groups']);
        Route::put('/groups/{id}', [AdminController::class, 'updateGroup']);

        // File Operations
        Route::get('/files', [AdminController::class, 'files']);
        Route::post('/files/{id}/retry', [AdminController::class, 'retryFile']);

        // Logs
        Route::get('/logs', [AdminController::class, 'logs']);
    });
});
