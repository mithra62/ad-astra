<?php

namespace Tests\Unit\Models\User;

use App\Models\User;
use App\Models\User\OauthToken;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OauthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_user_oauth_tokens_table(): void
    {
        $this->assertEquals('user_oauth_tokens', (new OauthToken)->getTable());
    }

    public function test_casts_scopes_to_array(): void
    {
        $token = OauthToken::factory()->create(['scopes' => ['read', 'write']]);

        $this->assertIsArray($token->scopes);
        $this->assertEquals(['read', 'write'], $token->scopes);
    }

    public function test_casts_meta_to_array(): void
    {
        $token = OauthToken::factory()->create(['meta' => ['foo' => 'bar']]);

        $this->assertIsArray($token->meta);
        $this->assertEquals(['foo' => 'bar'], $token->meta);
    }

    public function test_casts_expires_at_to_datetime(): void
    {
        $token = OauthToken::factory()->create(['expires_at' => now()->addHour()]);

        $this->assertInstanceOf(Carbon::class, $token->expires_at);
    }

    public function test_casts_revoked_at_to_datetime(): void
    {
        $token = OauthToken::factory()->revoked()->create();

        $this->assertInstanceOf(Carbon::class, $token->revoked_at);
    }

    public function test_user_relationship_is_belongs_to(): void
    {
        $token = OauthToken::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $token->user());
    }

    public function test_user_relationship_returns_owner(): void
    {
        $user = User::factory()->create();
        $token = OauthToken::factory()->for($user)->create();

        $this->assertEquals($user->id, $token->user->id);
    }

    public function test_scope_provider_filters_by_provider_name(): void
    {
        $github = OauthToken::factory()->create(['provider' => 'github']);
        $google = OauthToken::factory()->create(['provider' => 'google']);

        $results = OauthToken::query()->provider('github')->get();

        $this->assertTrue($results->contains($github));
        $this->assertFalse($results->contains($google));
    }

    public function test_scope_active_excludes_revoked_tokens(): void
    {
        $active = OauthToken::factory()->create(['revoked_at' => null]);
        $revoked = OauthToken::factory()->revoked()->create();

        $results = OauthToken::query()->active()->get();

        $this->assertTrue($results->contains($active));
        $this->assertFalse($results->contains($revoked));
    }

    public function test_scope_expired_returns_expired_tokens(): void
    {
        $expired = OauthToken::factory()->expired()->create();
        $valid = OauthToken::factory()->create(['expires_at' => now()->addHour()]);

        $results = OauthToken::query()->expired()->get();

        $this->assertTrue($results->contains($expired));
        $this->assertFalse($results->contains($valid));
    }

    public function test_scope_expired_excludes_tokens_with_null_expires_at(): void
    {
        $noExpiry = OauthToken::factory()->create(['expires_at' => null]);

        $results = OauthToken::query()->expired()->get();

        $this->assertFalse($results->contains($noExpiry));
    }

    public function test_scope_expiring_soon_returns_tokens_within_window(): void
    {
        $soonToken = OauthToken::factory()->create(['expires_at' => now()->addSeconds(200)]);
        $laterToken = OauthToken::factory()->create(['expires_at' => now()->addHours(2)]);

        $results = OauthToken::query()->expiringSoon(300)->get();

        $this->assertTrue($results->contains($soonToken));
        $this->assertFalse($results->contains($laterToken));
    }

    public function test_scope_oidc_identity_filters_by_issuer_and_subject(): void
    {
        $match = OauthToken::factory()->create(['issuer' => 'https://auth.example.com', 'subject' => 'user-123']);
        $noMatch = OauthToken::factory()->create(['issuer' => 'https://auth.example.com', 'subject' => 'user-456']);

        $results = OauthToken::query()->oidcIdentity('https://auth.example.com', 'user-123')->get();

        $this->assertTrue($results->contains($match));
        $this->assertFalse($results->contains($noMatch));
    }

    public function test_is_expired_returns_true_when_expires_at_is_past(): void
    {
        $token = OauthToken::factory()->expired()->create();

        $this->assertTrue($token->isExpired());
    }

    public function test_is_expired_returns_false_when_expires_at_is_future(): void
    {
        $token = OauthToken::factory()->create(['expires_at' => now()->addHour()]);

        $this->assertFalse($token->isExpired());
    }

    public function test_is_expired_returns_false_when_no_expires_at(): void
    {
        $token = OauthToken::factory()->create(['expires_at' => null]);

        $this->assertFalse($token->isExpired());
    }

    public function test_is_active_returns_true_for_valid_non_revoked_token(): void
    {
        $token = OauthToken::factory()->create([
            'expires_at' => now()->addHour(),
            'revoked_at' => null,
        ]);

        $this->assertTrue($token->isActive());
    }

    public function test_is_active_returns_false_when_revoked(): void
    {
        $token = OauthToken::factory()->revoked()->create(['expires_at' => now()->addHour()]);

        $this->assertFalse($token->isActive());
    }

    public function test_is_active_returns_false_when_expired(): void
    {
        $token = OauthToken::factory()->expired()->create(['revoked_at' => null]);

        $this->assertFalse($token->isActive());
    }

    public function test_revoke_sets_revoked_at_timestamp(): void
    {
        $token = OauthToken::factory()->create(['revoked_at' => null]);

        $token->revoke();

        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertInstanceOf(Carbon::class, $token->fresh()->revoked_at);
    }

    public function test_mark_used_sets_last_used_at_timestamp(): void
    {
        $token = OauthToken::factory()->create(['last_used_at' => null]);

        $token->markUsed();

        $this->assertNotNull($token->fresh()->last_used_at);
        $this->assertInstanceOf(Carbon::class, $token->fresh()->last_used_at);
    }

    public function test_has_scope_returns_true_for_existing_scope(): void
    {
        $token = OauthToken::factory()->create(['scopes' => ['read', 'write', 'admin']]);

        $this->assertTrue($token->hasScope('read'));
        $this->assertTrue($token->hasScope('admin'));
    }

    public function test_has_scope_returns_false_for_missing_scope(): void
    {
        $token = OauthToken::factory()->create(['scopes' => ['read']]);

        $this->assertFalse($token->hasScope('write'));
        $this->assertFalse($token->hasScope('delete'));
    }

    public function test_has_scope_returns_false_when_scopes_is_null(): void
    {
        $token = OauthToken::factory()->create(['scopes' => null]);

        $this->assertFalse($token->hasScope('read'));
    }
}
