<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LogRequestResponse
{
    /**
     * @var string[]
     */
    private array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'api_token',
        'access_token',
        'refresh_token',
        'id_token',
        'secret',
        'client_secret',
        'authorization',
        'cookie',
        'set-cookie',
    ];

    /**
     * @var string[]
     */
    private array $sensitiveHeaders = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-csrf-token',
        'x-xsrf-token',
    ];

    private int $maxJsonLength = 4000;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        ApiLog::create([
            'request_route' => $request->getPathInfo(),
            'method' => $request->method(),
            'user_id' => Auth::id(),
            'request_payload' => $this->encodeForLog(
                $this->sanitizeValue($request->all())
            ),
            'request_headers' => $this->encodeForLog(
                $this->sanitizeHeaders($request->headers->all())
            ),
            'response_payload' => $this->summarizeResponse($response),
            'response_headers' => $this->encodeForLog(
                $this->sanitizeHeaders($response->headers->all())
            ),
            'response_status_code' => $response->status(),
        ]);

        return $response;
    }

    private function sanitizeValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitizeValue(
                    $childValue,
                    is_string($childKey) ? $childKey : null
                );
            }

            return $sanitized;
        }

        if (is_string($value) && strlen($value) > $this->maxJsonLength) {
            return substr($value, 0, $this->maxJsonLength) . '...[truncated]';
        }

        return $value;
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $key => $value) {
            $normalizedKey = strtolower($key);

            if (in_array($normalizedKey, $this->sensitiveHeaders, true)) {
                $sanitized[$normalizedKey] = '[REDACTED]';
                continue;
            }

            $sanitized[$normalizedKey] = $this->sanitizeValue($value, $normalizedKey);
        }

        return $sanitized;
    }

    private function summarizeResponse(SymfonyResponse $response): ?string
    {
        $payload = [
            'content_type' => $response->headers->get('Content-Type'),
            'content_length' => strlen((string) $response->getContent()),
        ];

        if ($response instanceof JsonResponse) {
            $payload['body'] = $this->sanitizeValue($response->getData(true));
        } elseif ($response->isClientError() || $response->isServerError()) {
            $payload['body_preview'] = $this->truncate((string) $response->getContent());
        }

        return $this->encodeForLog($payload);
    }

    private function encodeForLog(mixed $value): string
    {
        return $this->truncate(
            json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null'
        );
    }

    private function truncate(string $value): string
    {
        if (strlen($value) <= $this->maxJsonLength) {
            return $value;
        }

        return substr($value, 0, $this->maxJsonLength) . '...[truncated]';
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower($key);

        foreach ($this->sensitiveKeys as $sensitiveKey) {
            if ($normalizedKey === $sensitiveKey || str_contains($normalizedKey, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
