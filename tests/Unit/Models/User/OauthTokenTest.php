<?php

namespace Tests\Unit\Models\User;

use App\Models\User;
use App\Models\User\OauthToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OauthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_token_has_fillable_attributes(): void
    {
        $token = new OauthToken();
        $fillable = [
            'user_id',
            'provider',
            'provider_account',
            'provider_user_id',
            'issuer',
            'subject',
            'id_token',
            'access_token',
            'refresh_token',
            'token_type',
            'expires_at',
            'scopes',
            'meta',
            'revoked_at',
            'last_used_at',
        ];
        $this->assertEquals($fillable, $token->getFillable());
    }

    public function test_oauth_token_casts_attributes(): void
    {
        $token = new OauthToken();
        $casts = $token->getCasts();

        $this->assertEquals('array', $casts['scopes']);
        $this->assertEquals('array', $casts['meta']);
        $this->assertEquals('datetime', $casts['expires_at']);
        $this->assertEquals('datetime', $casts['revoked_at']);
        $this->assertEquals('datetime', $casts['last_used_at']);
    }

    public function test_oauth_token_has_correct_table_name(): void
    {
        $token = new OauthToken();
        $this->assertEquals('user_oauth_tokens', $token->getTable());
    }

    public function test_oauth_token_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $token = OauthToken::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $token->user);
        $this->assertEquals($user->id, $token->user_id);
    }

    public function test_scope_provider(): void
    {
        OauthToken::factory()->create(['provider' => 'google']);
        OauthToken::factory()->create(['provider' => 'github']);

        $this->assertCount(1, OauthToken::provider('google')->get());
        $this->assertEquals('google', OauthToken::provider('google')->first()->provider);
    }

    public function test_scope_active(): void
    {
        OauthToken::factory()->create(['revoked_at' => null]);
        OauthToken::factory()->create(['revoked_at' => now()]);

        $this->assertCount(1, OauthToken::active()->get());
    }

    public function test_scope_expired(): void
    {
        OauthToken::factory()->create(['expires_at' => now()->subDay()]);
        OauthToken::factory()->create(['expires_at' => now()->addDay()]);

        $this->assertCount(1, OauthToken::expired()->get());
    }

    public function test_scope_expiring_soon(): void
    {
        // Expiring in 2 minutes (soon)
        OauthToken::factory()->create(['expires_at' => now()->addMinutes(2)]);
        // Expiring in 10 minutes (not soon by default 300s/5m)
        OauthToken::factory()->create(['expires_at' => now()->addMinutes(10)]);

        $this->assertCount(1, OauthToken::expiringSoon()->get());
        $this->assertCount(2, OauthToken::expiringSoon(900)->get()); // 15 minutes
    }

    public function test_scope_oidc_identity(): void
    {
        OauthToken::factory()->create([
            'issuer' => 'https://accounts.google.com',
            'subject' => 'sub123'
        ]);
        OauthToken::factory()->create([
            'issuer' => 'https://github.com',
            'subject' => 'sub456'
        ]);

        $token = OauthToken::oidcIdentity('https://accounts.google.com', 'sub123')->first();
        $this->assertNotNull($token);
        $this->assertEquals('sub123', $token->subject);
    }

    public function test_is_expired(): void
    {
        $expiredToken = new OauthToken(['expires_at' => now()->subDay()]);
        $activeToken = new OauthToken(['expires_at' => now()->addDay()]);
        $noExpiryToken = new OauthToken(['expires_at' => null]);

        $this->assertTrue($expiredToken->isExpired());
        $this->assertFalse($activeToken->isExpired());
        $this->assertFalse($noExpiryToken->isExpired());
    }

    public function test_is_active(): void
    {
        $activeToken = new OauthToken([
            'revoked_at' => null,
            'expires_at' => now()->addDay()
        ]);
        $revokedToken = new OauthToken([
            'revoked_at' => now(),
            'expires_at' => now()->addDay()
        ]);
        $expiredToken = new OauthToken([
            'revoked_at' => null,
            'expires_at' => now()->subDay()
        ]);

        $this->assertTrue($activeToken->isActive());
        $this->assertFalse($revokedToken->isActive());
        $this->assertFalse($expiredToken->isActive());
    }

    public function test_revoke(): void
    {
        $token = OauthToken::factory()->create(['revoked_at' => null]);
        $token->revoke();

        $this->assertNotNull($token->revoked_at);
        $this->assertDatabaseHas('user_oauth_tokens', [
            'id' => $token->id,
        ]);
        $this->assertNotNull(OauthToken::find($token->id)->revoked_at);
    }

    public function test_mark_used(): void
    {
        $token = OauthToken::factory()->create(['last_used_at' => null]);
        $token->markUsed();

        $this->assertNotNull($token->last_used_at);
        $this->assertDatabaseHas('user_oauth_tokens', [
            'id' => $token->id,
        ]);
        $this->assertNotNull(OauthToken::find($token->id)->last_used_at);
    }

    public function test_has_scope(): void
    {
        $token = new OauthToken(['scopes' => ['read', 'write']]);

        $this->assertTrue($token->hasScope('read'));
        $this->assertTrue($token->hasScope('write'));
        $this->assertFalse($token->hasScope('delete'));

        $tokenNoScopes = new OauthToken(['scopes' => null]);
        $this->assertFalse($tokenNoScopes->hasScope('read'));
    }
}
