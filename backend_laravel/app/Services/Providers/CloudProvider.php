<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\File;

class CloudProvider
{
    public function uploadFinalFile(string $finalFilePath, string $fileId): array
    {
        $targetDir = storage_path('app/cloud-server');
        File::ensureDirectoryExists($targetDir);

        $destination = "{$targetDir}/{$fileId}.mp4";
        File::copy($finalFilePath, $destination);

        return [
            'provider' => 'cloudServer',
            'location' => $destination,
        ];
    }
}
