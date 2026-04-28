<?php

namespace App\Http\Controllers;

use App\Services\ChatFcmNotifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ChatbotApiController extends Controller
{
    public function userMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:5000'],
            'context' => ['nullable', 'array'],
        ]);

        $conversation = DB::table('chat_conversations')
            ->where('external_id', $data['conversation_id'])
            ->first(['id', 'external_id']);

        if (!$conversation) {
            $conversationId = DB::table('chat_conversations')->insertGetId([
                'external_id' => $data['conversation_id'],
                'channel' => 'website',
                'meta' => json_encode(['started_at' => now()->toIso8601String()]),
                'last_message_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $conversation = (object) [
                'id' => $conversationId,
                'external_id' => $data['conversation_id'],
            ];
        }

        $messageText = trim((string) $data['message']);

        $messageId = DB::table('chat_messages')->insertGetId([
            'conversation_id' => $conversation->id,
            'sender' => 'user',
            'message' => $messageText,
            'meta' => json_encode(['context' => $data['context'] ?? []]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('chat_conversations')
            ->where('id', $conversation->id)
            ->update([
                'last_message_at' => now(),
                'updated_at' => now(),
            ]);

        app()->terminating(function () use ($conversation, $messageId, $messageText) {
            try {
                app(ChatFcmNotifier::class)->notifyAudience(
                    $conversation->external_id,
                    'admin',
                    'New User Message',
                    $messageText,
                    [
                        'message_id' => $messageId,
                        'sender' => 'user',
                        'role' => 'user',
                        'event' => 'new_user_message',
                    ]
                );
            } catch (Throwable $e) {
                // Keep API fast even if FCM is unavailable.
            }
        });

        return response()->json([
            'conversation_id' => $conversation->external_id,
            'message_id' => $messageId,
            'role' => 'user',
            'text' => $messageText,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function adminConversations(Request $request): JsonResponse
    {
        if (!$this->authorizeAdmin($request)) {
            return response()->json(['message' => 'Unauthorized admin request.'], 403);
        }

        $conversations = DB::table('chat_conversations')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'external_id', 'channel', 'last_message_at']);

        $payload = $conversations->map(function ($conversation) {
            $lastMessage = DB::table('chat_messages')
                ->where('conversation_id', $conversation->id)
                ->orderByDesc('id')
                ->first(['sender', 'message', 'created_at']);

            return [
                'conversation_id' => $conversation->external_id,
                'channel' => $conversation->channel,
                'last_message_at' => $conversation->last_message_at
                    ? Carbon::parse($conversation->last_message_at)->toIso8601String()
                    : null,
                'last_message' => $lastMessage ? [
                    'sender' => $lastMessage->sender,
                    'text' => $lastMessage->message,
                    'created_at' => $lastMessage->created_at
                        ? Carbon::parse($lastMessage->created_at)->toIso8601String()
                        : null,
                ] : null,
            ];
        });

        return response()->json([
            'conversations' => $payload,
        ]);
    }

    public function adminReply(Request $request): JsonResponse
    {
        if (!$this->authorizeAdmin($request)) {
            return response()->json(['message' => 'Unauthorized admin request.'], 403);
        }

        $data = $request->validate([
            'conversation_id' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:5000'],
            'admin_name' => ['nullable', 'string', 'max:100'],
        ]);

        $conversation = DB::table('chat_conversations')
            ->where('external_id', $data['conversation_id'])
            ->first(['id', 'external_id']);

        if (!$conversation) {
            $conversationId = DB::table('chat_conversations')->insertGetId([
                'external_id' => $data['conversation_id'],
                'channel' => 'website',
                'meta' => json_encode(['started_at' => now()->toIso8601String()]),
                'last_message_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $conversation = (object) [
                'id' => $conversationId,
                'external_id' => $data['conversation_id'],
            ];
        }

        $messageText = trim((string) $data['message']);
        $adminName = trim((string) ($data['admin_name'] ?? 'Admin Support'));

        $messageId = DB::table('chat_messages')->insertGetId([
            'conversation_id' => $conversation->id,
            'sender' => 'admin',
            'message' => $messageText,
            'meta' => json_encode(['admin_name' => $adminName]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('chat_conversations')
            ->where('id', $conversation->id)
            ->update([
                'last_message_at' => now(),
                'updated_at' => now(),
            ]);

        app()->terminating(function () use ($conversation, $messageId, $adminName, $messageText) {
            try {
                app(ChatFcmNotifier::class)->notifyAudience(
                    $conversation->external_id,
                    'user',
                    $adminName,
                    $messageText,
                    [
                        'message_id' => $messageId,
                        'sender' => 'admin',
                        'admin_name' => $adminName,
                        'role' => 'admin',
                        'event' => 'admin_reply',
                    ]
                );
            } catch (Throwable $e) {
                // Keep API fast even if FCM is unavailable.
            }
        });

        return response()->json([
            'conversation_id' => $conversation->external_id,
            'message_id' => $messageId,
            'role' => 'admin',
            'text' => $messageText,
            'admin_name' => $adminName,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'string', 'max:100'],
        ]);

        $conversation = DB::table('chat_conversations')
            ->where('external_id', $data['conversation_id'])
            ->first(['id', 'external_id']);

        if (!$conversation) {
            return response()->json([
                'conversation_id' => $data['conversation_id'],
                'messages' => [],
            ]);
        }

        $messages = DB::table('chat_messages')
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get(['id', 'sender', 'message', 'created_at'])
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'role' => $msg->sender,
                    'text' => $msg->message,
                    'created_at' => $msg->created_at ? Carbon::parse($msg->created_at)->toIso8601String() : null,
                ];
            });

        return response()->json([
            'conversation_id' => $conversation->external_id,
            'messages' => $messages,
        ]);
    }

    public function stream(Request $request)
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'string', 'max:100'],
            'last_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $conversationId = $data['conversation_id'];
        $lastId = (int) ($data['last_id'] ?? 0);

        return response()->stream(function () use ($conversationId, $lastId) {
            $startedAt = microtime(true);
            $cursor = $lastId;

            while ((microtime(true) - $startedAt) < 25) {
                if (connection_aborted()) {
                    break;
                }

                $conversation = DB::table('chat_conversations')
                    ->where('external_id', $conversationId)
                    ->first(['id']);

                if ($conversation) {
                    $freshMessages = DB::table('chat_messages')
                        ->where('conversation_id', $conversation->id)
                        ->where('id', '>', $cursor)
                        ->orderBy('id')
                        ->limit(20)
                        ->get(['id', 'sender', 'message', 'created_at']);

                    foreach ($freshMessages as $msg) {
                        $cursor = (int) $msg->id;

                        echo "event: message\n";
                        echo 'data: ' . json_encode([
                            'id' => $msg->id,
                            'role' => $msg->sender,
                            'text' => $msg->message,
                            'created_at' => $msg->created_at ? Carbon::parse($msg->created_at)->toIso8601String() : null,
                        ]) . "\n\n";
                    }
                }

                echo "event: heartbeat\n";
                echo "data: {}\n\n";

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }

                flush();
                usleep(800000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function vapidKey(): JsonResponse
    {
        $key = (string) env('WEBPUSH_VAPID_PUBLIC_KEY', '');

        if ($key === '') {
            return response()->json([
                'error' => 'VAPID public key not configured',
            ], 500);
        }

        return response()->json([
            'key' => $key,
        ]);
    }

    public function registerBrowserSubscription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'string', 'max:100'],
            'audience' => ['required', 'string', 'in:user,admin'],
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'content_encoding' => ['nullable', 'string', 'max:50'],
            'meta' => ['nullable', 'array'],
        ]);

        $existing = DB::table('chat_browser_subscriptions')
            ->where('endpoint', $data['endpoint'])
            ->first(['id']);

        $payload = [
            'conversation_external_id' => $data['conversation_id'],
            'audience' => $data['audience'],
            'endpoint' => $data['endpoint'],
            'public_key' => data_get($data, 'keys.p256dh'),
            'auth_token' => data_get($data, 'keys.auth'),
            'content_encoding' => $data['content_encoding'] ?? 'aesgcm',
            'last_seen_at' => now(),
            'meta' => json_encode($data['meta'] ?? []),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('chat_browser_subscriptions')
                ->where('id', $existing->id)
                ->update($payload);

            $id = $existing->id;
        } else {
            $id = DB::table('chat_browser_subscriptions')->insertGetId(array_merge($payload, [
                'created_at' => now(),
            ]));
        }

        return response()->json([
            'status' => 'registered',
            'id' => $id,
        ]);
    }

    public function registerDeviceToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'string', 'max:100'],
            'audience' => ['required', 'string', 'in:user,admin'],
            'platform' => ['nullable', 'string', 'max:30'],
            'device_token' => ['required', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
        ]);

        $existing = DB::table('chat_device_tokens')
            ->where('device_token', $data['device_token'])
            ->first(['id']);

        $payload = [
            'conversation_external_id' => $data['conversation_id'],
            'audience' => $data['audience'],
            'platform' => $data['platform'] ?? 'flutter',
            'device_token' => $data['device_token'],
            'last_seen_at' => now(),
            'meta' => json_encode($data['meta'] ?? []),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('chat_device_tokens')
                ->where('id', $existing->id)
                ->update($payload);

            $id = $existing->id;
        } else {
            $id = DB::table('chat_device_tokens')->insertGetId(array_merge($payload, [
                'created_at' => now(),
            ]));
        }

        return response()->json([
            'status' => 'registered',
            'id' => $id,
        ]);
    }

    private function authorizeAdmin(Request $request): bool
    {
        $configuredKey = (string) env('CHAT_ADMIN_KEY', '');
        $providedKey = (string) $request->header('X-Chat-Admin-Key', $request->input('admin_key', ''));

        if ($configuredKey === '') {
            return true;
        }

        return hash_equals($configuredKey, $providedKey);
    }
}
