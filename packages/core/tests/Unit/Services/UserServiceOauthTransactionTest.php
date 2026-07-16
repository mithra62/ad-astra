<?php

namespace Tests\Unit\Services;

use AdAstra\Models\User;
use AdAstra\Models\User\OauthToken;
use AdAstra\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class UserServiceOauthTransactionTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    public function test_upsert_returns_newly_created_token(): void
    {
        $user = User::factory()->create();

        $token = $this->service->upsertOauthToken($user, 'google', ['access_token' => 'tok123']);

        $this->assertInstanceOf(OauthToken::class, $token);
        $this->assertSame('tok123', $token->access_token);
        $this->assertSame('google', $token->provider);
        $this->assertNull($token->revoked_at);
    }

    // -------------------------------------------------------------------------
    // upsertOauthToken — transaction atomicity
    // -------------------------------------------------------------------------

    public function test_upsert_new_token_is_persisted(): void
    {
        $user = User::factory()->create();

        $token = $this->service->upsertOauthToken($user, 'google', ['access_token' => 'tok123']);

        $this->assertDatabaseHas('user_oauth_tokens', ['id' => $token->id, 'revoked_at' => null]);
    }

    public function test_revocation_is_rolled_back_when_insert_fails(): void
    {
        $user = User::factory()->create();
        $existing = OauthToken::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'revoked_at' => null,
        ]);

        // Force the INSERT to fail by making the connection throw after the UPDATE.
        DB::listen(function ($query) {
            if (str_contains(strtolower($query->sql), 'insert')) {
                throw new RuntimeException('Simulated INSERT failure');
            }
        });

        try {
            $this->service->upsertOauthToken($user, 'google', ['access_token' => 'new']);
        } catch (RuntimeException) {
            // expected
        }

        // The prior token must still be active — the UPDATE was rolled back.
        $this->assertNull(
            $existing->fresh()->revoked_at,
            'Revocation UPDATE must be rolled back when the subsequent INSERT fails',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserService::class);
    }

}
