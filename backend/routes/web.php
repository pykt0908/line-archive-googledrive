<?php

use Illuminate\Support\Facades\Route;

// 1. Serve files from storage (Local simulation disk fallback)
Route::get('/storage/{path}', function (string $path) {
    $filePath = storage_path('app/public/' . $path);
    if (!file_exists($filePath)) {
        abort(404, 'File not found');
    }
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'pdf' => 'application/pdf',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        default => 'application/octet-stream',
    };
    return response()->file($filePath, [
        'Content-Type' => $mime,
        'Content-Disposition' => 'inline; filename="' . rawurlencode(basename($filePath)) . '"',
    ]);
})->where('path', '.*');

// 2. Catch-all route to serve Vue 3 Single Page Application (SPA)
Route::get('/{any?}', function () {
    $candidates = [
        public_path('index.html'),
        base_path('../public_html/index.html'),
        isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/index.html' : null,
    ];

    foreach ($candidates as $path) {
        if ($path && file_exists($path)) {
            return response()->file($path);
        }
    }

    return response()->json([
        'message' => 'LINE Archive API Server is running.',
        'status' => 'OK'
    ]);
})->where('any', '^(?!api|storage).*$');
