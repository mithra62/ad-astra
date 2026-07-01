<?php

namespace Tests\Unit\Services;

use AdAstra\Models\User;
use AdAstra\Models\User\OauthToken;
use AdAstra\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceOauthRevocationTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserService::class);
    }

    // -------------------------------------------------------------------------
    // upsertOauthToken — bulk revocation of prior tokens
    // -------------------------------------------------------------------------

    public function test_upsert_revokes_all_active_tokens_for_same_provider(): void
    {
        $user = User::factory()->create();

        $t1 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);
        $t2 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);
        $t3 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);

        $this->service->upsertOauthToken($user, 'google', ['access_token' => 'new']);

        $this->assertNotNull($t1->fresh()->revoked_at);
        $this->assertNotNull($t2->fresh()->revoked_at);
        $this->assertNotNull($t3->fresh()->revoked_at);
    }

    public function test_upsert_all_prior_tokens_share_same_revoked_at_timestamp(): void
    {
        $user = User::factory()->create();

        $t1 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);
        $t2 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);

        $this->service->upsertOauthToken($user, 'google', ['access_token' => 'new']);

        // A bulk UPDATE sets the same timestamp for all rows; per-row revoke() calls
        // would each call now() independently, making exact equality unlikely.
        $this->assertEquals(
            $t1->fresh()->revoked_at->toDateTimeString(),
            $t2->fresh()->revoked_at->toDateTimeString(),
        );
    }

    public function test_upsert_does_not_revoke_tokens_for_other_providers(): void
    {
        $user = User::factory()->create();

        $github = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'github', 'revoked_at' => null]);

        $this->service->upsertOauthToken($user, 'google', ['access_token' => 'tok']);

        $this->assertNull($github->fresh()->revoked_at);
    }

    public function test_upsert_does_not_touch_already_revoked_tokens(): void
    {
        $user = User::factory()->create();
        $past = now()->subHour();
        $token = OauthToken::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'revoked_at' => $past,
        ]);

        $this->service->upsertOauthToken($user, 'google', ['access_token' => 'tok']);

        $this->assertEquals(
            $past->toDateTimeString(),
            $token->fresh()->revoked_at->toDateTimeString(),
            'A previously-revoked token should not have its revoked_at timestamp updated',
        );
    }

    // -------------------------------------------------------------------------
    // revokeAllOauthTokens — bulk revocation
    // -------------------------------------------------------------------------

    public function test_revoke_all_revokes_tokens_across_all_providers(): void
    {
        $user = User::factory()->create();

        $t1 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);
        $t2 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'github', 'revoked_at' => null]);
        $t3 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'twitter', 'revoked_at' => null]);

        $this->service->revokeAllOauthTokens($user);

        $this->assertNotNull($t1->fresh()->revoked_at);
        $this->assertNotNull($t2->fresh()->revoked_at);
        $this->assertNotNull($t3->fresh()->revoked_at);
    }

    public function test_revoke_all_tokens_share_same_revoked_at_timestamp(): void
    {
        $user = User::factory()->create();

        $t1 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);
        $t2 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'github', 'revoked_at' => null]);

        $this->service->revokeAllOauthTokens($user);

        $this->assertEquals(
            $t1->fresh()->revoked_at->toDateTimeString(),
            $t2->fresh()->revoked_at->toDateTimeString(),
        );
    }

    public function test_revoke_all_with_provider_filter_leaves_other_providers_untouched(): void
    {
        $user = User::factory()->create();

        $google1 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);
        $google2 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);
        $github = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'github', 'revoked_at' => null]);

        $this->service->revokeAllOauthTokens($user, 'google');

        $this->assertNotNull($google1->fresh()->revoked_at);
        $this->assertNotNull($google2->fresh()->revoked_at);
        $this->assertNull($github->fresh()->revoked_at);
    }
}
