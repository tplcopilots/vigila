<?php

namespace App\Services;

use App\Services\Providers\StorageProviderRouter;
use App\Services\Security\AuditLogger;
use Illuminate\Support\Facades\File;

class ChunkUploadService
{
    public function __construct(
        private readonly StorageProviderRouter $storageProviderRouter,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function saveReport(array $reportPayload): void
    {
        $reportPayload['createdAt'] = now()->toISOString();
        $reportPayload['provider'] = $reportPayload['provider'] ?? env('ACTIVE_STORAGE_PROVIDER', 'firebase');

        $reportPath = storage_path('app/reports/reports.jsonl');
        File::ensureDirectoryExists(dirname($reportPath));
        File::append($reportPath, json_encode($reportPayload).PHP_EOL);

        $this->auditLogger->info('service.report.persisted', [
            'report_id' => $reportPayload['reportId'] ?? null,
            'provider' => $reportPayload['provider'] ?? null,
            'storage_path' => $reportPath,
        ]);
    }

    public function registerUploadSession(string $fileId, int $totalChunks, ?string $provider = null): void
    {
        $chunkDir = storage_path("app/chunks/{$fileId}");
        File::ensureDirectoryExists($chunkDir);

        $metaPath = "{$chunkDir}/meta.json";
        $meta = [];
        if (File::exists($metaPath)) {
            try {
                $meta = json_decode((string) File::get($metaPath), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $meta = [];
            }
        }

        $meta['fileId'] = $fileId;
        $meta['totalChunks'] = $totalChunks;
        $meta['provider'] = $provider ?: ($meta['provider'] ?? env('ACTIVE_STORAGE_PROVIDER', 'firebase'));
        $meta['updatedAt'] = now()->toISOString();
        $meta['createdAt'] = $meta['createdAt'] ?? now()->toISOString();

        File::put($metaPath, json_encode($meta, JSON_PRETTY_PRINT));
    }

    public function saveChunk(string $fileId, int $chunkIndex, string $payloadBase64): array
    {
        $chunkDir = storage_path("app/chunks/{$fileId}");
        File::ensureDirectoryExists($chunkDir);

        $chunkPath = "{$chunkDir}/{$chunkIndex}.part";
        if (File::exists($chunkPath)) {
            $this->auditLogger->warning('service.chunk.duplicate', [
                'file_id' => $fileId,
                'chunk_index' => $chunkIndex,
                'chunk_path' => $chunkPath,
            ]);
            return ['duplicate' => true, 'path' => $chunkPath];
        }

        $decoded = base64_decode($payloadBase64, true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64 payload');
        }

        File::put($chunkPath, $decoded);

        $this->auditLogger->info('service.chunk.persisted', [
            'file_id' => $fileId,
            'chunk_index' => $chunkIndex,
            'chunk_size_bytes' => strlen($decoded),
            'chunk_path' => $chunkPath,
        ]);

        return ['duplicate' => false, 'path' => $chunkPath];
    }

    public function getChunkStatus(string $fileId, int $totalChunks): array
    {
        $chunkDir = storage_path("app/chunks/{$fileId}");
        if (!File::isDirectory($chunkDir)) {
            $missing = range(0, max(0, $totalChunks - 1));
            return [
                'uploadedIndexes' => [],
                'missingIndexes' => $missing,
            ];
        }

        $uploaded = [];
        $files = File::files($chunkDir);
        foreach ($files as $file) {
            $name = $file->getFilename();
            if (!str_ends_with($name, '.part')) {
                continue;
            }

            $indexRaw = str_replace('.part', '', $name);
            if (ctype_digit($indexRaw)) {
                $uploaded[] = (int) $indexRaw;
            }
        }

        sort($uploaded);
        $uploadedSet = array_flip($uploaded);

        $missing = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!isset($uploadedSet[$i])) {
                $missing[] = $i;
            }
        }

        return [
            'uploadedIndexes' => $uploaded,
            'missingIndexes' => $missing,
        ];
    }

    public function listFinalUploads(): array
    {
        $finalDir = storage_path('app/final');
        if (!File::isDirectory($finalDir)) {
            return [];
        }

        $files = File::files($finalDir);
        $uploads = [];
        foreach ($files as $file) {
            $name = $file->getFilename();
            $path = $file->getPathname();
            $uploads[] = [
                'name' => $name,
                'sizeBytes' => File::size($path),
                'updatedAt' => date(DATE_ATOM, $file->getMTime()),
                'extension' => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
            ];
        }

        usort($uploads, static fn (array $a, array $b) => strcmp($b['updatedAt'], $a['updatedAt']));
        return $uploads;
    }

    public function getUploadAnalytics(): array
    {
        $chunkRoot = storage_path('app/chunks');
        $runningChunks = 0;
        $pendingChunks = 0;
        $pendingByProvider = [
            'firebase' => 0,
            'oneDrive' => 0,
            'cloudServer' => 0,
        ];

        if (File::isDirectory($chunkRoot)) {
            foreach (File::directories($chunkRoot) as $dir) {
                $fileId = basename($dir);
                $metaPath = $dir.'/meta.json';
                $meta = [];
                if (File::exists($metaPath)) {
                    try {
                        $meta = json_decode((string) File::get($metaPath), true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable) {
                        $meta = [];
                    }
                }

                $total = max(0, (int) ($meta['totalChunks'] ?? 0));
                if ($total <= 0) {
                    continue;
                }

                $status = $this->getChunkStatus($fileId, $total);
                $uploadedCount = count($status['uploadedIndexes']);
                $missingCount = count($status['missingIndexes']);

                $runningChunks += $uploadedCount;
                $pendingChunks += $missingCount;

                if ($missingCount > 0) {
                    $provider = (string) ($meta['provider'] ?? env('ACTIVE_STORAGE_PROVIDER', 'firebase'));
                    if (!array_key_exists($provider, $pendingByProvider)) {
                        $pendingByProvider[$provider] = 0;
                    }
                    $pendingByProvider[$provider] += 1;
                }
            }
        }

        $files = $this->listFinalUploads();
        $typeCounts = [];
        foreach ($files as $file) {
            $extension = strtolower((string) ($file['extension'] ?? 'unknown'));
            if ($extension === '') {
                $extension = 'unknown';
            }
            if (!isset($typeCounts[$extension])) {
                $typeCounts[$extension] = 0;
            }
            $typeCounts[$extension] += 1;
        }
        ksort($typeCounts);

        return [
            'chunkState' => [
                'pendingChunks' => $pendingChunks,
                'runningChunks' => $runningChunks,
                'completedFiles' => count($files),
            ],
            'pendingByProvider' => $pendingByProvider,
            'fileTypeCounts' => $typeCounts,
        ];
    }

    public function getUploadHistoryPaginated(int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $files = $this->listFinalUploads();
        $items = array_map(static function (array $file): array {
            return [
                'dateTime' => $file['updatedAt'] ?? null,
                'name' => $file['name'] ?? null,
                'type' => strtoupper((string) ($file['extension'] ?? 'UNKNOWN')),
                'status' => 'COMPLETED',
            ];
        }, $files);

        return $this->paginateItems($items, $page, $perPage);
    }

    public function getUploadLogsPaginated(int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $logFiles = glob(storage_path('logs/upload_audit*.log')) ?: [];
        usort($logFiles, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $entries = [];

        foreach ($logFiles as $logFile) {
            $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines) || count($lines) === 0) {
                continue;
            }

            for ($index = count($lines) - 1; $index >= 0; $index--) {
                $line = (string) $lines[$index];
                if (!str_contains($line, 'file_id') && !str_contains($line, 'report_id')) {
                    continue;
                }

                $time = null;
                $level = 'INFO';
                $event = '';
                $context = [];
                $extra = [];

                if (preg_match('/^\[([^\]]+)\]\s+[^.]+\.([A-Z]+):\s+([^\{\[]*?)(?:\s+(\{.*?\}))?(?:\s+(\[.*\]))?$/i', $line, $matches) === 1) {
                    $time = $matches[1] ?? null;
                    $level = strtoupper($matches[2] ?? 'INFO');
                    $event = trim($matches[3] ?? '');
                    $contextRaw = trim((string) ($matches[4] ?? ''));
                    if ($contextRaw !== '') {
                        $decoded = json_decode($contextRaw, true);
                        if (is_array($decoded)) {
                            $context = $decoded;
                        }
                    }

                    $extraRaw = trim((string) ($matches[5] ?? ''));
                    if ($extraRaw !== '') {
                        $decodedExtra = json_decode($extraRaw, true);
                        if (is_array($decodedExtra)) {
                            $extra = $decodedExtra;
                        }
                    }
                }

                $fileId = $context['file_id'] ?? $context['report_id'] ?? null;
                if (!is_string($fileId) || trim($fileId) === '') {
                    if (preg_match('/"file_id"\s*:\s*"([^"]+)"/i', $line, $idMatch) === 1) {
                        $fileId = $idMatch[1];
                    } elseif (preg_match('/"report_id"\s*:\s*"([^"]+)"/i', $line, $idMatch) === 1) {
                        $fileId = $idMatch[1];
                    }
                }

                if (!is_string($fileId) || trim($fileId) === '') {
                    continue;
                }

                $entries[] = [
                    'dateTime' => $time,
                    'fileId' => $fileId,
                    'level' => $level,
                    'event' => $event,
                    'message' => $line,
                    'details' => [
                        'dateTime' => $time,
                        'level' => $level,
                        'event' => $event,
                        'fileId' => $fileId,
                        'context' => $context,
                        'extra' => $extra,
                        'raw' => $line,
                    ],
                ];
            }
        }

        return $this->paginateItems($entries, $page, $perPage);
    }

    private function paginateItems(array $items, int $page, int $perPage): array
    {
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $lastPage);
        $offset = ($currentPage - 1) * $perPage;

        return [
            'items' => array_values(array_slice($items, $offset, $perPage)),
            'pagination' => [
                'page' => $currentPage,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
        ];
    }

    private function resolveFinalExtension(?string $originalFileName, ?string $mimeType): string
    {
        $ext = strtolower((string) pathinfo((string) $originalFileName, PATHINFO_EXTENSION));
        if ($ext !== '' && preg_match('/^[a-z0-9]{1,10}$/', $ext) === 1) {
            return $ext;
        }

        $mime = strtolower((string) $mimeType);
        $map = [
            'image/png' => 'png',
            'image/jpg' => 'jpg',
            'image/jpeg' => 'jpeg',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'video/mp4' => 'mp4',
            'video/x-msvideo' => 'avi',
            'video/avi' => 'avi',
            'video/mpeg' => 'mpeg',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        ];

        return $map[$mime] ?? 'bin';
    }

    public function finalizeUpload(
        string $fileId,
        int $totalChunks,
        ?string $provider = null,
        ?string $originalFileName = null,
        ?string $mimeType = null,
    ): array
    {
        $chunkDir = storage_path("app/chunks/{$fileId}");
        $extension = $this->resolveFinalExtension($originalFileName, $mimeType);
        $finalPath = storage_path("app/final/{$fileId}.{$extension}");

        File::ensureDirectoryExists(dirname($finalPath));

        $finalHandle = fopen($finalPath, 'wb');
        if ($finalHandle === false) {
            throw new \RuntimeException('Failed to create final output file');
        }

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = "{$chunkDir}/{$i}.part";
                if (!File::exists($chunkPath)) {
                    throw new \RuntimeException("Missing chunk {$i}");
                }

                $chunkHandle = fopen($chunkPath, 'rb');
                if ($chunkHandle === false) {
                    throw new \RuntimeException("Failed to read chunk {$i}");
                }

                stream_copy_to_stream($chunkHandle, $finalHandle);
                fclose($chunkHandle);
            }
        } finally {
            fclose($finalHandle);
        }

        $providerName = $provider ?: env('ACTIVE_STORAGE_PROVIDER', 'firebase');
        $storageResult = $this->storageProviderRouter->uploadFinalFile(
            provider: $providerName,
            finalFilePath: $finalPath,
            fileId: $fileId,
        );

        if (File::isDirectory($chunkDir)) {
            File::deleteDirectory($chunkDir);
        }

        $this->auditLogger->info('service.finalize.completed', [
            'file_id' => $fileId,
            'provider' => $providerName,
            'original_file_name' => $originalFileName,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'final_path' => $finalPath,
            'final_size_bytes' => File::exists($finalPath) ? File::size($finalPath) : null,
            'storage_location' => $storageResult['location'] ?? null,
        ]);

        return [
            'provider' => $providerName,
            'storage' => $storageResult,
        ];
    }
}
