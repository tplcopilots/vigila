<?php

use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verify.upload.signature'])->group(function () {
    Route::post('/report', [UploadController::class, 'report']);
    Route::post('/upload/chunk', [UploadController::class, 'uploadChunk']);
    Route::get('/upload/status', [UploadController::class, 'status']);
    Route::post('/upload/finalize', [UploadController::class, 'finalizeUpload']);
});
