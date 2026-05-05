<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\LogRequestResponse;
use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LogRequestResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redacts_sensitive_request_and_response_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/api/v1/test', 'POST', [
            'email' => 'user@example.com',
            'password' => 'super-secret',
            'profile' => [
                'refresh_token' => 'refresh-secret',
            ],
        ]);
        $request->headers->set('Authorization', 'Bearer secret-token');
        $request->headers->set('X-Trace-Id', 'trace-123');

        $middleware = new LogRequestResponse();

        $middleware->handle($request, function () {
            return response()->json([
                'access_token' => 'access-secret',
                'message' => 'ok',
            ], 201)->header('Set-Cookie', 'session=secret');
        });

        $log = ApiLog::query()->sole();

        $requestPayload = json_decode($log->request_payload, true);
        $requestHeaders = json_decode($log->request_headers, true);
        $responseHeaders = json_decode($log->response_headers, true);

        $this->assertSame('[REDACTED]', $requestPayload['password']);
        $this->assertSame('[REDACTED]', $requestPayload['profile']['refresh_token']);
        $this->assertSame('user@example.com', $requestPayload['email']);
        $this->assertSame('[REDACTED]', $requestHeaders['authorization']);
        $this->assertSame('trace-123', $requestHeaders['x-trace-id'][0]);
        $this->assertSame('[REDACTED]', $responseHeaders['set-cookie']);
        $this->assertSame(201, $log->response_status_code);
        $this->assertSame($user->id, $log->user_id);
    }

    public function test_it_truncates_large_logged_content(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/api/v1/test', 'POST', [
            'notes' => str_repeat('a', 5000),
        ]);

        $middleware = new LogRequestResponse();

        $middleware->handle($request, function () {
            return response()->json([
                'message' => str_repeat('b', 5000),
            ]);
        });

        $log = ApiLog::query()->sole();

        $this->assertStringContainsString('[truncated]', $log->request_payload);
    }
}
