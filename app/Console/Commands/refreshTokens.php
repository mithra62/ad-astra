<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class refreshTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:refresh-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
