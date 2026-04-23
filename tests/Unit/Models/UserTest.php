<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\User\OauthToken;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new User;

        $this->assertEquals(['name', 'email', 'password'], $model->getFillable());
    }

    public function test_hides_password_and_remember_token(): void
    {
        $model = new User;

        $this->assertContains('password', $model->getHidden());
        $this->assertContains('remember_token', $model->getHidden());
    }

    public function test_casts_email_verified_at_to_datetime(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
    }

    public function test_email_verified_at_can_be_null(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertNull($user->email_verified_at);
    }

    public function test_oauth_tokens_relationship_is_has_many(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(HasMany::class, $user->oauthTokens());
    }

    public function test_oauth_tokens_relationship_returns_user_tokens(): void
    {
        $user = User::factory()->create();
        OauthToken::factory()->for($user)->create(['provider' => 'github']);
        OauthToken::factory()->for($user)->create(['provider' => 'google']);

        $this->assertCount(2, $user->oauthTokens);
    }

    public function test_oauth_token_for_returns_active_token_for_provider(): void
    {
        $user = User::factory()->create();
        $token = OauthToken::factory()->for($user)->create([
            'provider' => 'github',
            'expires_at' => now()->addHour(),
            'revoked_at' => null,
        ]);

        $result = $user->oauthTokenFor('github');

        $this->assertNotNull($result);
        $this->assertEquals($token->id, $result->id);
    }

    public function test_oauth_token_for_returns_null_for_nonexistent_provider(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->oauthTokenFor('github'));
    }

    public function test_oauth_token_for_returns_null_when_token_is_revoked(): void
    {
        $user = User::factory()->create();
        OauthToken::factory()->revoked()->for($user)->create(['provider' => 'github']);

        $this->assertNull($user->oauthTokenFor('github'));
    }

    public function test_oauth_token_for_returns_expired_token_when_not_revoked(): void
    {
        $user = User::factory()->create();
        $token = OauthToken::factory()->expired()->for($user)->create(['provider' => 'github']);

        $result = $user->oauthTokenFor('github');

        $this->assertNotNull($result);
        $this->assertEquals($token->id, $result->id);
    }

    public function test_oauth_token_for_returns_latest_by_expiry(): void
    {
        $user = User::factory()->create();
        OauthToken::factory()->for($user)->create([
            'provider' => 'github',
            'expires_at' => now()->addHour(),
        ]);
        $newer = OauthToken::factory()->for($user)->create([
            'provider' => 'github',
            'expires_at' => now()->addHours(2),
        ]);

        $result = $user->oauthTokenFor('github');

        $this->assertEquals($newer->id, $result->id);
    }

    public function test_avatar_returns_non_empty_string(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $this->assertIsString($user->avatar());
        $this->assertNotEmpty($user->avatar());
    }

    public function test_avatar_returns_empty_string_when_no_email(): void
    {
        $user = new User(['email' => null]);

        $this->assertEquals('', $user->avatar());
    }

    public function test_has_field_values_morph_many_from_fieldable_trait(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(MorphMany::class, $user->fieldValues());
    }
}
