<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChunkUploadService;
use App\Services\Security\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    public function __construct(
        private readonly ChunkUploadService $chunkUploadService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function report(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reportId' => ['required', 'string', 'max:128'],
                'provider' => ['nullable', 'string', 'max:32'],
                'report' => ['required', 'array'],
            ]);

            $this->chunkUploadService->saveReport($validated);
            $this->auditLogger->info('api.report.saved', [
                'report_id' => $validated['reportId'],
                'provider' => $validated['provider'] ?? env('ACTIVE_STORAGE_PROVIDER', 'firebase'),
            ], $request);

            return response()->json([
                'success' => true,
                'reportId' => $validated['reportId'],
            ]);
        } catch (ValidationException $exception) {
            $this->auditLogger->warning('api.report.validation_failed', [
                'errors' => $exception->errors(),
            ], $request);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            $this->auditLogger->error('api.report.error', [
                'message' => $exception->getMessage(),
            ], $request);
            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
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
                'provider' => ['nullable', 'string', 'max:32'],
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
                $this->auditLogger->warning('api.chunk.duplicate', [
                    'file_id' => $validated['fileId'],
                    'chunk_index' => $validated['chunkIndex'],
                ], $request);
                return response()->json([
                    'error' => 'Chunk already uploaded',
                ], 409);
            }

            $this->auditLogger->info('api.chunk.saved', [
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
            $this->auditLogger->warning('api.chunk.validation_failed', [
                'errors' => $exception->errors(),
            ], $request);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            $this->auditLogger->error('api.chunk.error', [
                'message' => $exception->getMessage(),
            ], $request);
            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
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

            $this->auditLogger->info('api.chunk.status', [
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
            $this->auditLogger->warning('api.status.validation_failed', [
                'errors' => $exception->errors(),
            ], $request);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            $this->auditLogger->error('api.status.error', [
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
                $this->auditLogger->warning('api.finalize.blocked_missing_chunks', [
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

            $this->auditLogger->info('api.finalize.success', [
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
            $this->auditLogger->warning('api.finalize.validation_failed', [
                'errors' => $exception->errors(),
            ], $request);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            $this->auditLogger->error('api.finalize.error', [
                'message' => $exception->getMessage(),
            ], $request);
            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        }
    }
}
