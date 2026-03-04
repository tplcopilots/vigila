<?php

use App\Http\Controllers\Web\ChunkUploadPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'timestamp' => now()->toISOString(),
    ]);
});

Route::get('/upload', [ChunkUploadPageController::class, 'index'])->name('upload.page');
Route::get('/upload/files', [ChunkUploadPageController::class, 'uploads'])->name('upload.files');
Route::get('/upload/analytics', [ChunkUploadPageController::class, 'analytics'])->name('upload.analytics');
Route::get('/upload/history', [ChunkUploadPageController::class, 'history'])->name('upload.history');
Route::get('/upload/logs', [ChunkUploadPageController::class, 'logs'])->name('upload.logs');
Route::get('/upload/files/{name}/view', [ChunkUploadPageController::class, 'viewFile'])->where('name', '.*')->name('upload.files.view');
Route::get('/upload/files/{name}/download', [ChunkUploadPageController::class, 'downloadFile'])->where('name', '.*')->name('upload.files.download');
Route::post('/upload/init', [ChunkUploadPageController::class, 'init'])->name('upload.init');
Route::get('/upload/status', [ChunkUploadPageController::class, 'status'])->name('upload.status');
Route::post('/upload/chunk', [ChunkUploadPageController::class, 'uploadChunk'])->name('upload.chunk');
Route::post('/upload/finalize', [ChunkUploadPageController::class, 'finalizeUpload'])->name('upload.finalize');
