<?php

namespace App\Services\Providers;

class StorageProviderRouter
{
    public function __construct(
        private readonly CloudProvider $cloudProvider,
        private readonly FirebaseProvider $firebaseProvider,
        private readonly OneDriveProvider $oneDriveProvider,
    ) {
    }

    public function uploadFinalFile(string $provider, string $finalFilePath, string $fileId): array
    {
        return match ($provider) {
            'cloudServer' => $this->cloudProvider->uploadFinalFile($finalFilePath, $fileId),
            'firebase' => $this->firebaseProvider->uploadFinalFile($finalFilePath, $fileId),
            'oneDrive' => $this->oneDriveProvider->uploadFinalFile($finalFilePath, $fileId),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }
}
