<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public function info(string $event, array $context = [], ?Request $request = null): void
    {
        Log::channel('upload_audit')->info($event, $this->buildContext($context, $request));
    }

    public function warning(string $event, array $context = [], ?Request $request = null): void
    {
        Log::channel('upload_audit')->warning($event, $this->buildContext($context, $request));
    }

    public function error(string $event, array $context = [], ?Request $request = null): void
    {
        Log::channel('upload_audit')->error($event, $this->buildContext($context, $request));
    }

    private function buildContext(array $context, ?Request $request): array
    {
        if ($request === null) {
            return $context;
        }

        return array_merge([
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'user_agent' => (string) $request->userAgent(),
            'request_id' => (string) $request->header('x-request-id', ''),
        ], $context);
    }
}
