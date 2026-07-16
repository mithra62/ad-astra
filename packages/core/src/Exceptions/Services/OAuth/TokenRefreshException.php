<?php

namespace AdAstra\Exceptions\Services\OAuth;

use RuntimeException;

class TokenRefreshException extends RuntimeException
{
    public static function notRefreshable(string $reason): self
    {
        return new self("Token is not refreshable: {$reason}");
    }

    public static function providerNotConfigured(string $provider): self
    {
        return new self("OAuth provider not configured: {$provider}");
    }

    public static function httpFailed(string $provider, int $status, string $body): self
    {
        return new self("Token refresh HTTP failure for {$provider} ({$status}): {$body}");
    }

    public static function invalidResponse(string $provider, string $reason): self
    {
        return new self("Token refresh invalid response for {$provider}: {$reason}");
    }
}
