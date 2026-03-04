<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class FirebaseProvider
{
    public function uploadFinalFile(string $finalFilePath, string $fileId): array
    {
        $bucket = (string) env('FIREBASE_STORAGE_BUCKET', '');
        $accessToken = (string) env('FIREBASE_ACCESS_TOKEN', '');

        if ($bucket !== '' && $accessToken !== '') {
            $objectName = "vigilance-videos/{$fileId}.mp4";
            $uploadUrl = "https://storage.googleapis.com/upload/storage/v1/b/{$bucket}/o";

            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'video/mp4'])
                ->withBody((string) file_get_contents($finalFilePath), 'video/mp4')
                ->post($uploadUrl, [
                    'uploadType' => 'media',
                    'name' => $objectName,
                ]);

            if ($response->successful()) {
                return [
                    'provider' => 'firebase',
                    'location' => "gs://{$bucket}/{$objectName}",
                ];
            }
        }

        $fallbackDir = storage_path('app/firebase-fallback');
        File::ensureDirectoryExists($fallbackDir);

        $destination = "{$fallbackDir}/{$fileId}.mp4";
        File::copy($finalFilePath, $destination);

        return [
            'provider' => 'firebase',
            'location' => $destination,
            'note' => 'Uploaded to local firebase-fallback. Set FIREBASE_STORAGE_BUCKET and FIREBASE_ACCESS_TOKEN for real Firebase Storage upload.',
        ];
    }
}
