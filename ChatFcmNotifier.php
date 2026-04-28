<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class ChatFcmNotifier
{
    public function notifyAudience(string $conversationId, string $audience, string $title, string $body, array $data = []): bool
    {
        $serverKey = (string) config('services.fcm.server_key', '');
        $connectTimeoutSeconds = (int) config('services.fcm.connect_timeout', 1);
        $requestTimeoutSeconds = (int) config('services.fcm.request_timeout', 2);

        if ($serverKey === '') {
            return false;
        }

        $tokens = DB::table('chat_device_tokens')
            ->where('conversation_external_id', $conversationId)
            ->where('audience', $audience)
            ->pluck('device_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])
                ->connectTimeout(max(1, $connectTimeoutSeconds))
                ->timeout(max(1, $requestTimeoutSeconds))
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'registration_ids' => $tokens,
                    'priority' => 'high',
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_merge([
                        'conversation_id' => $conversationId,
                        'audience' => $audience,
                        'channel' => 'chatbot',
                    ], $data),
                ]);

            return $response->successful();
        } catch (Throwable $e) {
            return false;
        }
    }
}
