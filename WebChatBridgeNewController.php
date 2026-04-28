<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class WebChatBridgeNewController extends Controller
{
    public function __construct()
    {
        $this->pageTitle = 'Web Chat Bridge';
    }

    public function index()
    {
        return view('messages.chat', $this->data);
    }

    public function conversations(): JsonResponse
    {
        try {
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
                    'last_message_at' => optional($conversation->last_message_at)->toIso8601String(),
                    'last_message' => $lastMessage ? $lastMessage->message : null,
                    'last_sender' => $lastMessage ? $lastMessage->sender : null,
                ];
            });

            return response()->json([
                'conversations' => $payload,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load conversations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'string', 'max:100'],
        ]);

        try {
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
                        'created_at' => optional($msg->created_at)->toIso8601String(),
                    ];
                });

            return response()->json([
                'conversation_id' => $conversation->external_id,
                'messages' => $messages,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load chat history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:5000'],
            'admin_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
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

            $adminName = trim((string) ($data['admin_name'] ?? 'Admin Support'));
            $messageText = trim((string) $data['message']);

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

            return response()->json([
                'conversation_id' => $conversation->external_id,
                'message_id' => $messageId,
                'role' => 'admin',
                'text' => $messageText,
                'admin_name' => $adminName,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send reply',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function stream(Request $request)
    {
        $data = $request->validate([
            'last_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $lastId = (int) ($data['last_id'] ?? 0);

        return response()->stream(function () use ($lastId) {
            $startedAt = microtime(true);
            $cursor = $lastId;

            while ((microtime(true) - $startedAt) < 25) {
                if (connection_aborted()) {
                    break;
                }

                $freshMessages = DB::table('chat_messages as m')
                    ->join('chat_conversations as c', 'c.id', '=', 'm.conversation_id')
                    ->where('m.id', '>', $cursor)
                    ->orderBy('m.id')
                    ->limit(20)
                    ->get([
                        'm.id',
                        'm.sender',
                        'm.message',
                        'm.created_at',
                        'c.external_id as conversation_id',
                    ]);

                foreach ($freshMessages as $msg) {
                    $cursor = (int) $msg->id;

                    echo "event: message\n";
                    echo 'data: ' . json_encode([
                        'id' => $msg->id,
                        'conversation_id' => $msg->conversation_id,
                        'role' => $msg->sender,
                        'text' => $msg->message,
                        'created_at' => optional($msg->created_at)->toIso8601String(),
                    ]) . "\n\n";
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

}
