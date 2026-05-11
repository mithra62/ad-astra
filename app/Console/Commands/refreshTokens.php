<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RefreshTokens extends Command
{
    protected $signature = 'app:refresh-tokens';

    protected $description = 'Refresh expiring OAuth tokens';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        //another... Refresh before calling an API
//        $token = $user->oauthTokenFor('google'); // from your User model helper
//
//        if ($token) {
//            $token = app(TokenRefreshService::class)->refresh($token); // refresh if needed
//            // use $token->access_token
//        }
//
//        Refresh all active tokens for a provider
//        $svc = app(TokenRefreshService::class);
//
//        OauthToken::query()
//            ->provider('stripe')
//            ->active()
//            ->get()
//            ->each(fn ($t) => $svc->tryRefresh($t));

    }
}
