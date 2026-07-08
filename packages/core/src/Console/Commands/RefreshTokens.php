<?php

namespace AdAstra\Console\Commands;

use AdAstra\Models\User\OauthToken;
use AdAstra\Services\OAuth\TokenRefreshService;
use Illuminate\Console\Command;

class RefreshTokens extends Command
{
    protected $signature = 'adastra:refresh-tokens
                            {--provider= : Limit refresh to a specific provider}
                            {--window=300 : Refresh tokens expiring within this many seconds}';

    protected $description = 'Refresh expiring OAuth tokens';

    public function handle(TokenRefreshService $svc): void
    {
        $provider = $this->option('provider');
        $window = (int)$this->option('window');

        $query = OauthToken::query()
            ->active()
            ->whereNotNull('refresh_token')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addSeconds($window));

        if ($provider) {
            $query->provider($provider);
        }

        $tokens = $query->get();

        if ($tokens->isEmpty()) {
            $this->info('No tokens need refreshing.');
            return;
        }

        $this->info("Found {$tokens->count()} token(s) to refresh.");

        $refreshed = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            $result = $svc->tryRefresh($token, force: true);
            if ($result !== null) {
                $refreshed++;
            } else {
                $failed++;
            }
        }

        $this->info("Done — refreshed: {$refreshed}, failed: {$failed}.");
    }
}
