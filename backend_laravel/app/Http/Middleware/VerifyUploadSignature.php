<?php

namespace App\Http\Middleware;

use App\Services\Security\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class VerifyUploadSignature
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = (string) env('API_KEY', 'change-me-api-key');
        $signingSecret = (string) env('REQUEST_SIGNING_SECRET', 'change-me-signing-secret');
        $maxSkewMs = (int) env('SIGNATURE_MAX_SKEW_MS', 300000);

        $incomingApiKey = (string) $request->header('x-api-key', '');
        if ($incomingApiKey === '' || !hash_equals($apiKey, $incomingApiKey)) {
            $this->auditLogger->warning('upload.auth.failed.api_key', [], $request);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $timestampHeader = (string) $request->header('x-timestamp', '');
        $nonce = (string) $request->header('x-nonce', '');
        $signature = (string) $request->header('x-signature', '');

        if ($timestampHeader === '' || $nonce === '' || $signature === '') {
            $this->auditLogger->warning('upload.auth.failed.missing_headers', [], $request);
            return response()->json(['error' => 'Missing signature headers'], 401);
        }

        if (!ctype_digit($timestampHeader)) {
            $this->auditLogger->warning('upload.auth.failed.invalid_timestamp', [
                'timestamp' => $timestampHeader,
            ], $request);
            return response()->json(['error' => 'Invalid timestamp'], 401);
        }

        $timestamp = (int) $timestampHeader;
        $nowMs = (int) round(microtime(true) * 1000);
        if (abs($nowMs - $timestamp) > $maxSkewMs) {
            $this->auditLogger->warning('upload.auth.failed.expired_timestamp', [
                'timestamp' => $timestamp,
                'now_ms' => $nowMs,
                'max_skew_ms' => $maxSkewMs,
            ], $request);
            return response()->json(['error' => 'Expired request timestamp'], 401);
        }

        if ($this->isReplayNonce($nonce, $nowMs, $maxSkewMs)) {
            $this->auditLogger->warning('upload.auth.failed.replay_nonce', [
                'nonce' => $nonce,
            ], $request);
            return response()->json(['error' => 'Replay detected'], 409);
        }

        $method = strtoupper($request->method());
        $path = '/'.ltrim($request->path(), '/');
        $rawBody = $request->getContent() ?: '';
        $bodyHash = hash('sha256', $rawBody);

        $canonical = implode("\n", [
            $method,
            $path,
            $timestampHeader,
            $nonce,
            $bodyHash,
        ]);

        $expectedSignature = hash_hmac('sha256', $canonical, $signingSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            $this->auditLogger->warning('upload.auth.failed.invalid_signature', [
                'nonce' => $nonce,
            ], $request);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $this->markNonce($nonce, $nowMs + $maxSkewMs);
        $this->auditLogger->info('upload.auth.success', [
            'nonce' => $nonce,
        ], $request);

        return $next($request);
    }

    private function nonceStorePath(): string
    {
        return storage_path('app/security/nonces.json');
    }

    private function isReplayNonce(string $nonce, int $nowMs, int $maxSkewMs): bool
    {
        $path = $this->nonceStorePath();

        if (!File::exists($path)) {
            return false;
        }

        $payload = json_decode((string) File::get($path), true);
        if (!is_array($payload)) {
            return false;
        }

        $dirty = false;
        foreach ($payload as $knownNonce => $expiryMs) {
            if (!is_int($expiryMs) && !ctype_digit((string) $expiryMs)) {
                unset($payload[$knownNonce]);
                $dirty = true;
                continue;
            }

            if ((int) $expiryMs <= $nowMs) {
                unset($payload[$knownNonce]);
                $dirty = true;
            }
        }

        if ($dirty) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($payload));
        }

        return isset($payload[$nonce]);
    }

    private function markNonce(string $nonce, int $expiryMs): void
    {
        $path = $this->nonceStorePath();
        File::ensureDirectoryExists(dirname($path));

        $payload = [];
        if (File::exists($path)) {
            $decoded = json_decode((string) File::get($path), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $payload[$nonce] = $expiryMs;
        File::put($path, json_encode($payload));
    }
}
