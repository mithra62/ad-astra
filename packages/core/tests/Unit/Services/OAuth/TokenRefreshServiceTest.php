<?php

namespace Tests\Unit\Services\OAuth;

use AdAstra\Exceptions\Services\OAuth\TokenRefreshException;
use AdAstra\Models\User\OauthToken;
use AdAstra\Services\OAuth\TokenRefreshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TokenRefreshServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://oauth.example.test/token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['oauth_providers.acme' => [
            'token_url' => self::TOKEN_URL,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
        ]]);
    }

    private function service(): TokenRefreshService
    {
        return new TokenRefreshService();
    }

    private function makeToken(array $overrides = []): OauthToken
    {
        return OauthToken::factory()->create(array_merge([
            'provider' => 'acme',
            'access_token' => 'old-access',
            'refresh_token' => 'old-refresh',
            'expires_at' => now()->subMinute(),
            'scopes' => [],
            'meta' => [],
        ], $overrides));
    }

    private function fakeSuccessfulResponse(array $overrides = []): void
    {
        Http::fake([self::TOKEN_URL => Http::response(array_merge([
            'access_token' => 'new-access',
            'refresh_token' => 'new-refresh',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], $overrides))]);
    }

    // -------------------------------------------------------------------------
    // Guards: tokens that must not be refreshed
    // -------------------------------------------------------------------------

    public function test_refresh_rejects_revoked_tokens(): void
    {
        $token = $this->makeToken(['revoked_at' => now()]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('revoked');

        $this->service()->refresh($token);
    }

    public function test_refresh_rejects_tokens_without_a_refresh_token(): void
    {
        $token = $this->makeToken(['refresh_token' => null]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('missing refresh_token');

        $this->service()->refresh($token);
    }

    // -------------------------------------------------------------------------
    // No-op path: still-valid tokens
    // -------------------------------------------------------------------------

    public function test_refresh_is_a_noop_when_token_is_still_valid(): void
    {
        Http::fake();
        $token = $this->makeToken(['expires_at' => now()->addHour()]);

        $result = $this->service()->refresh($token);

        $this->assertSame('old-access', $result->access_token);
        Http::assertNothingSent();
    }

    public function test_token_without_known_expiry_is_assumed_valid(): void
    {
        Http::fake();
        $token = $this->makeToken(['expires_at' => null]);

        $this->service()->refresh($token);

        Http::assertNothingSent();
    }

    public function test_leeway_forces_refresh_of_tokens_expiring_inside_the_window(): void
    {
        $this->fakeSuccessfulResponse();
        $token = $this->makeToken(['expires_at' => now()->addSeconds(60)]);

        $result = $this->service()->refresh($token, leewaySeconds: 120);

        $this->assertSame('new-access', $result->access_token);
    }

    public function test_force_refreshes_a_still_valid_token(): void
    {
        $this->fakeSuccessfulResponse();
        $token = $this->makeToken(['expires_at' => now()->addHour()]);

        $result = $this->service()->refresh($token, force: true);

        $this->assertSame('new-access', $result->access_token);
    }

    // -------------------------------------------------------------------------
    // Provider configuration guards
    // -------------------------------------------------------------------------

    public function test_refresh_fails_for_unconfigured_provider(): void
    {
        $token = $this->makeToken(['provider' => 'unknown-provider']);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('not configured: unknown-provider');

        $this->service()->refresh($token);
    }

    public function test_refresh_fails_when_provider_has_no_token_url(): void
    {
        config(['oauth_providers.acme' => [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
        ]]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('missing token_url');

        $this->service()->refresh($this->makeToken());
    }

    public function test_refresh_fails_when_client_credentials_are_missing(): void
    {
        config(['oauth_providers.acme' => [
            'token_url' => self::TOKEN_URL,
        ]]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('missing client credentials');

        $this->service()->refresh($this->makeToken());
    }

    public function test_token_url_can_come_from_token_meta(): void
    {
        config(['oauth_providers.acme' => [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
        ]]);
        $this->fakeSuccessfulResponse();

        $token = $this->makeToken(['meta' => ['token_endpoint' => self::TOKEN_URL]]);

        $result = $this->service()->refresh($token);

        $this->assertSame('new-access', $result->access_token);
        Http::assertSent(fn (Request $request) => $request->url() === self::TOKEN_URL);
    }

    // -------------------------------------------------------------------------
    // Successful refresh
    // -------------------------------------------------------------------------

    public function test_successful_refresh_persists_the_new_token_fields(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->fakeSuccessfulResponse(['scope' => 'read write']);

        $token = $this->makeToken();
        $result = $this->service()->refresh($token);

        $this->assertSame('new-access', $result->access_token);
        $this->assertSame('new-refresh', $result->refresh_token);
        $this->assertSame('Bearer', $result->token_type);
        $this->assertSame(['read', 'write'], $result->scopes);
        $this->assertTrue($result->expires_at->equalTo(now()->addSeconds(3600)));
        $this->assertNotNull($result->last_used_at);

        $this->assertDatabaseHas('user_oauth_tokens', [
            'id' => $token->id,
            'access_token' => 'new-access',
        ]);
    }

    public function test_refresh_sends_form_encoded_grant_with_client_credentials(): void
    {
        $this->fakeSuccessfulResponse();
        $token = $this->makeToken(['scopes' => ['read', 'write']]);

        $this->service()->refresh($token);

        Http::assertSent(function (Request $request) {
            return $request->url() === self::TOKEN_URL
                && $request->isForm()
                && $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === 'old-refresh'
                && $request['client_id'] === 'client-id'
                && $request['client_secret'] === 'client-secret'
                && $request['scope'] === 'read write';
        });
    }

    public function test_old_refresh_token_is_kept_when_provider_does_not_rotate(): void
    {
        $this->fakeSuccessfulResponse(['refresh_token' => null]);

        $result = $this->service()->refresh($this->makeToken());

        $this->assertSame('old-refresh', $result->refresh_token);
    }

    public function test_existing_expiry_is_kept_when_provider_omits_expires_in(): void
    {
        $this->fakeSuccessfulResponse(['expires_in' => null]);
        $expiry = now()->subMinute()->startOfSecond();

        $result = $this->service()->refresh($this->makeToken(['expires_at' => $expiry]));

        $this->assertTrue($result->expires_at->equalTo($expiry));
    }

    public function test_refresh_response_is_merged_into_meta_without_secrets(): void
    {
        $this->fakeSuccessfulResponse(['id_token' => 'new-id-token']);

        $result = $this->service()->refresh($this->makeToken(['meta' => ['existing' => 'kept']]));

        $this->assertSame('kept', $result->meta['existing']);
        $this->assertArrayHasKey('refreshed_at', $result->meta);
        $this->assertArrayNotHasKey('access_token', $result->meta['refresh_response']);
        $this->assertArrayNotHasKey('refresh_token', $result->meta['refresh_response']);
        $this->assertArrayNotHasKey('id_token', $result->meta['refresh_response']);
    }

    public function test_scope_arrays_and_delimited_strings_are_normalized(): void
    {
        $this->fakeSuccessfulResponse(['scope' => ['read', 'admin']]);

        $result = $this->service()->refresh($this->makeToken());

        $this->assertSame(['read', 'admin'], $result->scopes);
    }

    public function test_previous_scopes_are_kept_when_provider_omits_scope(): void
    {
        $this->fakeSuccessfulResponse();

        $result = $this->service()->refresh($this->makeToken(['scopes' => ['read']]));

        $this->assertSame(['read'], $result->scopes);
    }

    // -------------------------------------------------------------------------
    // Failure responses
    // -------------------------------------------------------------------------

    public function test_http_error_response_throws_with_status_and_body(): void
    {
        Http::fake([self::TOKEN_URL => Http::response('upstream broke', 500)]);

        try {
            $this->service()->refresh($this->makeToken());
            $this->fail('Expected TokenRefreshException');
        } catch (TokenRefreshException $e) {
            $this->assertStringContainsString('acme', $e->getMessage());
            $this->assertStringContainsString('500', $e->getMessage());
        }
    }

    public function test_non_json_response_throws_invalid_response(): void
    {
        Http::fake([self::TOKEN_URL => Http::response('<html>not json</html>', 200, [
            'Content-Type' => 'text/html',
        ])]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('response was not JSON');

        $this->service()->refresh($this->makeToken());
    }

    public function test_json_response_without_access_token_throws_invalid_response(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['token_type' => 'Bearer'])]);

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('missing access_token');

        $this->service()->refresh($this->makeToken());
    }

    // -------------------------------------------------------------------------
    // tryRefresh
    // -------------------------------------------------------------------------

    public function test_try_refresh_returns_refreshed_token_on_success(): void
    {
        $this->fakeSuccessfulResponse();

        $result = $this->service()->tryRefresh($this->makeToken());

        $this->assertNotNull($result);
        $this->assertSame('new-access', $result->access_token);
    }

    public function test_try_refresh_swallows_failures_and_logs_a_warning(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message) => $message === 'OAuth token refresh failed');

        $result = $this->service()->tryRefresh($this->makeToken(['revoked_at' => now()]));

        $this->assertNull($result);
    }
}
