<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\User\OauthToken;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravolt\Avatar\Avatar;
use Mockery;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_fillable_attributes(): void
    {
        $user = new User();
        $fillable = ['name', 'email', 'title', 'phone', 'password'];
        $this->assertEquals($fillable, $user->getFillable());
    }

    public function test_user_has_hidden_attributes(): void
    {
        $user = new User();
        $hidden = ['password', 'remember_token'];
        $this->assertEquals($hidden, $user->getHidden());
    }

    public function test_user_casts_attributes(): void
    {
        $user = new User();
        $casts = $user->getCasts();
        $this->assertArrayHasKey('email_verified_at', $casts);
        $this->assertEquals('datetime', $casts['email_verified_at']);
        $this->assertArrayHasKey('password', $casts);
        $this->assertEquals('hashed', $casts['password']);
    }

    public function test_user_has_oauth_tokens_relationship(): void
    {
        $user = new User();
        $this->assertInstanceOf(HasMany::class, $user->oauthTokens());
        $this->assertInstanceOf(OauthToken::class, $user->oauthTokens()->getRelated());
    }

    public function test_oauth_token_for_returns_active_token_for_provider(): void
    {
        $user = User::factory()->create();

        // Active token
        $activeToken = OauthToken::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'access_token' => 'active-token',
            'expires_at' => now()->addHour(),
        ]);

        // Revoked token
        OauthToken::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'access_token' => 'revoked-token',
            'expires_at' => now()->addHour(),
            'revoked_at' => now(),
        ]);

        // Token for different provider
        OauthToken::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'github-token',
            'expires_at' => now()->addHour(),
        ]);

        $token = $user->oauthTokenFor('google');

        $this->assertNotNull($token);
        $this->assertEquals($activeToken->id, $token->id);
        $this->assertEquals('google', $token->provider);
    }

    public function test_oauth_token_for_returns_latest_expiring_token(): void
    {
        $user = User::factory()->create();

        $earlierToken = OauthToken::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'access_token' => 'earlier-token',
            'expires_at' => now()->addHour(),
        ]);

        $laterToken = OauthToken::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'access_token' => 'later-token',
            'expires_at' => now()->addHours(2),
        ]);

        $token = $user->oauthTokenFor('google');

        $this->assertNotNull($token);
        $this->assertEquals($laterToken->id, $token->id);
    }

    public function test_avatar_returns_gravatar_url_when_email_exists(): void
    {
        $user = new User(['email' => 'test@example.com']);

        $mockAvatar = Mockery::mock(Avatar::class);
        $mockAvatar->shouldReceive('create')->with('test@example.com')->andReturnSelf();
        $mockAvatar->shouldReceive('toGravatar')->andReturn('https://gravatar.com/avatar/hash');

        $this->app->instance(Avatar::class, $mockAvatar);

        $this->assertEquals('https://gravatar.com/avatar/hash', $user->avatar());
    }

    public function test_avatar_returns_empty_string_when_email_is_missing(): void
    {
        $user = new User();
        $this->assertEquals('', $user->avatar());
    }
}
