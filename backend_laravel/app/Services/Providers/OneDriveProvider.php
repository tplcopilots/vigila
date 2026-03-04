<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;

class OneDriveProvider
{
    public function uploadFinalFile(string $finalFilePath, string $fileId): array
    {
        $token = (string) env('ONEDRIVE_ACCESS_TOKEN', '');
        $folder = (string) env('ONEDRIVE_FOLDER', 'vigilance-videos');

        if ($token === '') {
            throw new \RuntimeException('ONEDRIVE_ACCESS_TOKEN is required for oneDrive provider');
        }

        $uploadUrl = "https://graph.microsoft.com/v1.0/me/drive/root:/{$folder}/{$fileId}.mp4:/content";

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'video/mp4'])
            ->withBody((string) file_get_contents($finalFilePath), 'video/mp4')
            ->put($uploadUrl);

        if (!$response->successful()) {
            throw new \RuntimeException('OneDrive upload failed: '.$response->status().' '.$response->body());
        }

        $payload = $response->json();

        return [
            'provider' => 'oneDrive',
            'location' => $payload['webUrl'] ?? $payload['id'] ?? 'uploaded',
        ];
    }
}
