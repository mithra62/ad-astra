<?php

namespace Tests\Unit\Services\OAuth;

use App\Exceptions\Services\OAuth\TokenRefreshException;
use App\Models\User\OauthToken;
use App\Services\OAuth\TokenRefreshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class TokenRefreshServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TokenRefreshService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TokenRefreshService();
    }

    public function test_refresh_throws_exception_if_token_is_revoked(): void
    {
        $token = OauthToken::factory()->create([
            'revoked_at' => now(),
        ]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('Token is not refreshable: token is revoked');

        $this->service->refresh($token);
    }

    public function test_refresh_throws_exception_if_refresh_token_is_missing(): void
    {
        $token = OauthToken::factory()->create([
            'refresh_token' => null,
        ]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('Token is not refreshable: missing refresh_token');

        $this->service->refresh($token);
    }

    public function test_refresh_returns_token_if_still_valid_and_not_forced(): void
    {
        $token = OauthToken::factory()->create([
            'expires_at' => now()->addMinutes(10),
            'refresh_token' => 'some-refresh-token',
        ]);

        Http::fake();

        $result = $this->service->refresh($token);

        $this->assertSame($token, $result);
        Http::assertNothingSent();
    }

    public function test_refresh_proceeds_if_token_is_expired(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'test-provider',
            'expires_at' => now()->subMinutes(1),
            'refresh_token' => 'old-refresh-token',
            'scopes' => ['read', 'write'],
        ]);

        Config::set('oauth_providers.test-provider', [
            'token_url' => 'https://auth.example.com/token',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        Http::fake([
            'https://auth.example.com/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'scope' => 'read write',
            ], 200),
        ]);

        $result = $this->service->refresh($token);

        $this->assertEquals('new-access-token', $result->access_token);
        $this->assertEquals('new-refresh-token', $result->refresh_token);
        $this->assertNotNull($result->expires_at);
        $this->assertEquals(['read', 'write'], $result->scopes);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://auth.example.com/token' &&
                   $request['grant_type'] === 'refresh_token' &&
                   $request['refresh_token'] === 'old-refresh-token' &&
                   $request['client_id'] === 'test-client-id' &&
                   $request['client_secret'] === 'test-client-secret' &&
                   $request['scope'] === 'read write';
        });
    }

    public function test_refresh_proceeds_if_forced_even_if_valid(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'test-provider',
            'expires_at' => now()->addMinutes(10),
            'refresh_token' => 'old-refresh-token',
        ]);

        Config::set('oauth_providers.test-provider', [
            'token_url' => 'https://auth.example.com/token',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        Http::fake([
            'https://auth.example.com/token' => Http::response([
                'access_token' => 'new-access-token',
            ], 200),
        ]);

        $result = $this->service->refresh($token, force: true);

        $this->assertEquals('new-access-token', $result->access_token);
        Http::assertSentCount(1);
    }

    public function test_refresh_uses_meta_token_endpoint_if_config_missing_token_url(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'test-provider',
            'refresh_token' => 'old-refresh-token',
            'expires_at' => now()->subMinutes(1),
            'meta' => ['token_endpoint' => 'https://meta.example.com/token'],
        ]);

        Config::set('oauth_providers.test-provider', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        Http::fake([
            'https://meta.example.com/token' => Http::response(['access_token' => 'meta-access-token'], 200),
        ]);

        $result = $this->service->refresh($token);

        $this->assertEquals('meta-access-token', $result->access_token);
    }

    public function test_refresh_throws_exception_if_provider_not_configured(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'unknown-provider',
            'refresh_token' => 'some-token',
            'expires_at' => now()->subMinutes(1),
        ]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('OAuth provider not configured: unknown-provider');

        $this->service->refresh($token);
    }

    public function test_refresh_throws_exception_if_token_url_missing(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'test-provider',
            'refresh_token' => 'some-token',
            'expires_at' => now()->subMinutes(1),
            'meta' => [],
        ]);

        Config::set('oauth_providers.test-provider', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            // token_url is missing
        ]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('OAuth provider not configured: test-provider (missing token_url)');

        $this->service->refresh($token);
    }

    public function test_refresh_throws_exception_if_credentials_missing(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'test-provider',
            'refresh_token' => 'some-token',
            'expires_at' => now()->subMinutes(1),
        ]);

        Config::set('oauth_providers.test-provider', [
            'token_url' => 'https://auth.example.com/token',
            // client_id or client_secret missing
        ]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('OAuth provider not configured: test-provider (missing client credentials)');

        $this->service->refresh($token);
    }

    public function test_refresh_throws_exception_if_http_request_fails(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'test-provider',
            'refresh_token' => 'old-refresh-token',
            'expires_at' => now()->subMinutes(1),
        ]);

        Config::set('oauth_providers.test-provider', [
            'token_url' => 'https://auth.example.com/token',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        Http::fake([
            'https://auth.example.com/token' => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('Token refresh HTTP failure for test-provider (500): Internal Server Error');

        $this->service->refresh($token);
    }

    public function test_refresh_throws_exception_if_response_not_json(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'test-provider',
            'refresh_token' => 'old-refresh-token',
            'expires_at' => now()->subMinutes(1),
        ]);

        Config::set('oauth_providers.test-provider', [
            'token_url' => 'https://auth.example.com/token',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        Http::fake([
            'https://auth.example.com/token' => Http::response('not-json', 200),
        ]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('Token refresh invalid response for test-provider: response was not JSON');

        $this->service->refresh($token);
    }

    public function test_refresh_throws_exception_if_access_token_missing_in_response(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'test-provider',
            'refresh_token' => 'old-refresh-token',
            'expires_at' => now()->subMinutes(1),
        ]);

        Config::set('oauth_providers.test-provider', [
            'token_url' => 'https://auth.example.com/token',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        Http::fake([
            'https://auth.example.com/token' => Http::response(['not_access_token' => 'something'], 200),
        ]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('Token refresh invalid response for test-provider: missing access_token');

        $this->service->refresh($token);
    }

    public function test_try_refresh_returns_token_on_success(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'test-provider',
            'refresh_token' => 'old-refresh-token',
            'expires_at' => now()->subMinutes(1),
        ]);

        Config::set('oauth_providers.test-provider', [
            'token_url' => 'https://auth.example.com/token',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        Http::fake([
            'https://auth.example.com/token' => Http::response(['access_token' => 'new-access-token'], 200),
        ]);

        $result = $this->service->tryRefresh($token);

        $this->assertNotNull($result);
        $this->assertEquals('new-access-token', $result->access_token);
    }

    public function test_try_refresh_returns_null_and_logs_on_failure(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'test-provider',
            'refresh_token' => 'old-refresh-token',
            'expires_at' => now()->subMinutes(1),
        ]);

        // Missing config to trigger failure
        Config::set('oauth_providers.test-provider', null);

        Log::shouldReceive('warning')
            ->once()
            ->with('OAuth token refresh failed', Mockery::on(function ($data) use ($token) {
                return $data['provider'] === 'test-provider' &&
                       $data['token_id'] === $token->id &&
                       $data['user_id'] === $token->user_id;
            }));

        $result = $this->service->tryRefresh($token);

        $this->assertNull($result);
    }

    public function test_github_provider_adds_accept_json_header(): void
    {
        $token = OauthToken::factory()->create([
            'provider' => 'github',
            'refresh_token' => 'old-refresh-token',
            'expires_at' => now()->subMinutes(1),
        ]);

        Config::set('oauth_providers.github', [
            'token_url' => 'https://github.com/login/oauth/access_token',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'github-access-token'], 200),
        ]);

        $this->service->refresh($token);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Accept', 'application/json');
        });
    }
}
