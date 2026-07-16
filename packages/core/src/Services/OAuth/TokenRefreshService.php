<?php

namespace AdAstra\Services\OAuth;

use AdAstra\Exceptions\Services\OAuth\TokenRefreshException;
use AdAstra\Models\User\OauthToken;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TokenRefreshService
{
    /**
     * Convenience method: refresh and swallow failures (optional).
     */
    public function tryRefresh(OauthToken $token, bool $force = false, int $leewaySeconds = 120): ?OauthToken
    {
        try {
            return $this->refresh($token, $force, $leewaySeconds);
        } catch (Throwable $e) {
            Log::warning('OAuth token refresh failed', [
                'provider' => $token->provider,
                'token_id' => $token->id,
                'user_id' => $token->user_id,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Refresh a token if needed (or forced).
     *
     * @throws TokenRefreshException
     */
    public function refresh(OauthToken $token, bool $force = false, int $leewaySeconds = 120): OauthToken
    {
        if ($token->revoked_at !== null) {
            throw TokenRefreshException::notRefreshable('token is revoked');
        }

        if (empty($token->refresh_token)) {
            throw TokenRefreshException::notRefreshable('missing refresh_token');
        }

        if (!$force && $this->isStillValid($token, $leewaySeconds)) {
            return $token; // no-op
        }

        $provider = $token->provider;
        $cfg = config("oauth_providers.{$provider}");

        // Allow OIDC or custom providers to set token endpoint via meta (recommended)
        $tokenUrl = $cfg['token_url'] ?? null;
        if (!$tokenUrl) {
            $tokenUrl = data_get($token->meta, 'token_endpoint')
                ?: data_get($token->meta, 'discovery.token_endpoint');
        }

        if (!$cfg) {
            throw TokenRefreshException::providerNotConfigured($provider);
        }

        if (!$tokenUrl) {
            throw TokenRefreshException::providerNotConfigured("{$provider} (missing token_url)");
        }

        $clientId = $cfg['client_id'] ?? null;
        $clientSecret = $cfg['client_secret'] ?? null;

        if (!$clientId || !$clientSecret) {
            throw TokenRefreshException::providerNotConfigured("{$provider} (missing client credentials)");
        }

        $payload = array_merge([
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->refresh_token,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ], $cfg['extra'] ?? []);

        // Some providers support/require scope on refresh. If you stored scopes, you can pass them.
        if (!empty($token->scopes) && is_array($token->scopes)) {
            // Many providers want space-delimited scopes
            $payload['scope'] = implode(' ', $token->scopes);
        }

        $request = $this->httpClientFor($provider);

        try {
            $response = $request
                ->asForm() // most token endpoints expect x-www-form-urlencoded
                ->post($tokenUrl, $payload);
        } catch (RequestException $e) {
            throw TokenRefreshException::httpFailed($provider, $e->getCode() ?: $e->response->status(), (string)$e->response->body());
        } catch (Exception $e) {
            throw TokenRefreshException::httpFailed($provider, 0, $e->getMessage());
        }

        if (!$response->successful()) {
            throw TokenRefreshException::httpFailed($provider, $response->status(), (string)$response->body());
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw TokenRefreshException::invalidResponse($provider, 'response was not JSON');
        }

        // Normalize common OAuth2 fields
        $accessToken = $json['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw TokenRefreshException::invalidResponse($provider, 'missing access_token');
        }

        $newRefreshToken = $json['refresh_token'] ?? null; // not always returned
        $tokenType = $json['token_type'] ?? $token->token_type ?? 'Bearer';

        $expiresIn = $json['expires_in'] ?? null; // seconds
        $expiresAt = null;
        if (is_numeric($expiresIn)) {
            $expiresAt = now()->addSeconds((int)$expiresIn);
        }

        // Some OIDC providers may return a new id_token
        $idToken = $json['id_token'] ?? null;

        // Scope can come back as a string or array depending on provider
        $scopes = $this->normalizeScopes($json['scope'] ?? $json['scopes'] ?? null, $token->scopes);

        // Persist
        $token->forceFill([
            'access_token' => $accessToken,
            'refresh_token' => is_string($newRefreshToken) && $newRefreshToken !== '' ? $newRefreshToken : $token->refresh_token,
            'token_type' => is_string($tokenType) ? $tokenType : $token->token_type,
            'expires_at' => $expiresAt ?? $token->expires_at, // keep existing if provider doesn't send expires_in
            'id_token' => is_string($idToken) && $idToken !== '' ? $idToken : $token->id_token,
            'scopes' => $scopes,
            'last_used_at' => now(),
            // keep meta, but merge in the refresh response (handy for debugging/provider quirks)
            'meta' => array_merge($token->meta ?? [], [
                'refresh_response' => Arr::except($json, ['access_token', 'refresh_token', 'id_token']),
                'refreshed_at' => now()->toISOString(),
            ]),
        ])->save();

        return $token->refresh();
    }

    protected function isStillValid(OauthToken $token, int $leewaySeconds): bool
    {
        // If we don't know expiry, assume valid unless forced
        if ($token->expires_at === null) {
            return true;
        }

        return $token->expires_at->gt(now()->addSeconds($leewaySeconds));
    }

    protected function httpClientFor(string $provider): PendingRequest
    {
        $req = Http::timeout(15)
            ->retry(2, 300) // small retry for transient network failures
            ->acceptJson();

        // GitHub token endpoint often behaves best with explicit JSON accept
        if ($provider === 'github') {
            $req = $req->withHeaders([
                'Accept' => 'application/json',
            ]);
        }

        // Twitter uses Basic auth in some cases; if you need it, you can enable here.
        // Some Twitter setups want Authorization: Basic base64(client_id:client_secret)
        // We’re sending client_id/client_secret in the form body by default for simplicity.

        return $req;
    }

    /**
     * Normalize scope output to a string[] array.
     */
    protected function normalizeScopes(mixed $scopeField, mixed $fallback): array
    {
        if (is_array($scopeField)) {
            return array_values(array_filter(array_map('strval', $scopeField)));
        }

        if (is_string($scopeField) && $scopeField !== '') {
            // many providers return scopes space-delimited
            $parts = preg_split('/[\s,]+/', trim($scopeField)) ?: [];
            return array_values(array_filter($parts));
        }

        return is_array($fallback) ? $fallback : [];
    }
}
