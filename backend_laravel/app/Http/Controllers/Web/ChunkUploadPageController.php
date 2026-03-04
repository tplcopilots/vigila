<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ChunkUploadService;
use App\Services\Security\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChunkUploadPageController extends Controller
{
    public function __construct(
        private readonly ChunkUploadService $chunkUploadService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index()
    {
        return view('upload');
    }

    public function uploads(): JsonResponse
    {
        $uploads = $this->chunkUploadService->listFinalUploads();

        return response()->json([
            'success' => true,
            'files' => $uploads,
        ]);
    }

    public function analytics(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'analytics' => $this->chunkUploadService->getUploadAnalytics(),
        ]);
    }

    public function viewFile(string $name)
    {
        $safeName = basename($name);
        $path = storage_path("app/final/{$safeName}");

        if (!File::exists($path) || !File::isFile($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function downloadFile(string $name)
    {
        $safeName = basename($name);
        $path = storage_path("app/final/{$safeName}");

        if (!File::exists($path) || !File::isFile($path)) {
            abort(404);
        }

        return response()->download($path, $safeName);
    }

    public function init(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'totalChunks' => ['required', 'integer', 'min:1'],
                'provider' => ['nullable', 'string', 'max:32'],
                'fileId' => ['nullable', 'string', 'max:128'],
            ]);

            $fileId = $validated['fileId'] ?? (string) Str::uuid();
            $provider = $validated['provider'] ?? env('ACTIVE_STORAGE_PROVIDER', 'firebase');

            $this->chunkUploadService->registerUploadSession(
                fileId: $fileId,
                totalChunks: $validated['totalChunks'],
                provider: $provider,
            );

            $status = $this->chunkUploadService->getChunkStatus(
                fileId: $fileId,
                totalChunks: $validated['totalChunks'],
            );

            return response()->json([
                'success' => true,
                'fileId' => $fileId,
                'totalChunks' => $validated['totalChunks'],
                'uploadedIndexes' => $status['uploadedIndexes'],
                'missingIndexes' => $status['missingIndexes'],
                'provider' => $provider,
            ]);
        } catch (ValidationException $exception) {
            $this->auditLogger->warning('web.init.validation_failed', [
                'errors' => $exception->errors(),
            ], $request);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $exception->errors(),
            ], 422);
            $this->auditLogger->info('web.init.success', [
                'file_id' => $fileId,
                'total_chunks' => $validated['totalChunks'],
            ], $request);

        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fileId' => ['required', 'string', 'max:128'],
                'totalChunks' => ['required', 'integer', 'min:1'],
            ]);

            $status = $this->chunkUploadService->getChunkStatus(
                fileId: $validated['fileId'],
                totalChunks: $validated['totalChunks'],
            );

            $this->auditLogger->info('web.status.success', [
                'file_id' => $validated['fileId'],
                'total_chunks' => $validated['totalChunks'],
                'uploaded_count' => count($status['uploadedIndexes']),
                'missing_count' => count($status['missingIndexes']),
            ], $request);

            return response()->json([
                'success' => true,
                'fileId' => $validated['fileId'],
                'totalChunks' => $validated['totalChunks'],
                'uploadedIndexes' => $status['uploadedIndexes'],
                'missingIndexes' => $status['missingIndexes'],
            ]);
        } catch (ValidationException $exception) {
            $this->auditLogger->warning('web.status.validation_failed', [
                'errors' => $exception->errors(),
            ], $request);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $exception->errors(),
            ], 422);
        }
    }

    public function uploadChunk(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fileId' => ['required', 'string', 'max:128'],
                'chunkIndex' => ['required', 'integer', 'min:0'],
                'totalChunks' => ['required', 'integer', 'min:1'],
                'payload' => ['required', 'string'],
            ]);

            if ($validated['chunkIndex'] >= $validated['totalChunks']) {
                return response()->json([
                    'error' => 'Invalid chunkIndex or totalChunks value',
                ], 400);
            }

            $result = $this->chunkUploadService->saveChunk(
                fileId: $validated['fileId'],
                chunkIndex: $validated['chunkIndex'],
                payloadBase64: $validated['payload'],
            );

            if ($result['duplicate'] === true) {
                $this->auditLogger->warning('web.chunk.duplicate', [
                    'file_id' => $validated['fileId'],
                    'chunk_index' => $validated['chunkIndex'],
                ], $request);
                return response()->json([
                    'error' => 'Chunk already uploaded',
                ], 409);
            }

            $this->auditLogger->info('web.chunk.saved', [
                'file_id' => $validated['fileId'],
                'chunk_index' => $validated['chunkIndex'],
                'total_chunks' => $validated['totalChunks'],
                'payload_bytes_approx' => strlen((string) $validated['payload']),
            ], $request);

            return response()->json([
                'success' => true,
                'fileId' => $validated['fileId'],
                'chunkIndex' => $validated['chunkIndex'],
            ]);
        } catch (ValidationException $exception) {
            $this->auditLogger->warning('web.chunk.validation_failed', [
                'errors' => $exception->errors(),
            ], $request);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            $this->auditLogger->error('web.chunk.error', [
                'message' => $exception->getMessage(),
            ], $request);
            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function finalizeUpload(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fileId' => ['required', 'string', 'max:128'],
                'totalChunks' => ['required', 'integer', 'min:1'],
                'provider' => ['nullable', 'string', 'max:32'],
                'fileName' => ['nullable', 'string', 'max:255'],
                'mimeType' => ['nullable', 'string', 'max:255'],
            ]);

            $status = $this->chunkUploadService->getChunkStatus(
                fileId: $validated['fileId'],
                totalChunks: $validated['totalChunks'],
            );

            if (count($status['missingIndexes']) > 0) {
                $this->auditLogger->warning('web.finalize.blocked_missing_chunks', [
                    'file_id' => $validated['fileId'],
                    'missing_indexes' => $status['missingIndexes'],
                ], $request);
                return response()->json([
                    'error' => 'Cannot finalize upload. Missing chunks exist.',
                    'missingIndexes' => $status['missingIndexes'],
                    'uploadedIndexes' => $status['uploadedIndexes'],
                ], 409);
            }

            $result = $this->chunkUploadService->finalizeUpload(
                fileId: $validated['fileId'],
                totalChunks: $validated['totalChunks'],
                provider: $validated['provider'] ?? null,
                originalFileName: $validated['fileName'] ?? null,
                mimeType: $validated['mimeType'] ?? null,
            );

            $this->auditLogger->info('web.finalize.success', [
                'file_id' => $validated['fileId'],
                'provider' => $result['provider'],
                'location' => $result['storage']['location'] ?? null,
            ], $request);

            return response()->json([
                'success' => true,
                'fileId' => $validated['fileId'],
                'provider' => $result['provider'],
                'storage' => $result['storage'],
            ]);
        } catch (ValidationException $exception) {
            $this->auditLogger->warning('web.finalize.validation_failed', [
                'errors' => $exception->errors(),
            ], $request);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            $this->auditLogger->error('web.finalize.error', [
                'message' => $exception->getMessage(),
            ], $request);
            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        }
    }
}
