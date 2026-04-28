<?php

namespace Tests\Unit\Actions\User\Token;

use App\Actions\User\Token\CreateNewUserToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\NewAccessToken;
use Tests\TestCase;

class UserTokenActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CreateNewUserToken
    // -------------------------------------------------------------------------

    public function test_create_returns_new_access_token(): void
    {
        $user = User::factory()->create();
        $action = app(CreateNewUserToken::class);

        $result = $action->create($user, ['name' => 'My Token']);

        $this->assertInstanceOf(NewAccessToken::class, $result);
    }

    public function test_create_issues_token_with_given_name(): void
    {
        $user = User::factory()->create();
        $action = app(CreateNewUserToken::class);

        $token = $action->create($user, ['name' => 'API Token']);

        $this->assertEquals('API Token', $token->accessToken->name);
    }

    public function test_create_persists_token_to_database(): void
    {
        $user = User::factory()->create();
        $action = app(CreateNewUserToken::class);

        $action->create($user, ['name' => 'Stored Token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => 'user',
            'name' => 'Stored Token',
        ]);
    }

    public function test_create_sets_expiry_when_expires_at_is_provided(): void
    {
        $user = User::factory()->create();
        $expiresAt = Carbon::now()->addDays(30)->toDateTimeString();
        $action = app(CreateNewUserToken::class);

        $token = $action->create($user, ['name' => 'Expiring Token', 'expires_at' => $expiresAt]);

        $this->assertNotNull($token->accessToken->expires_at);
        $this->assertEquals(
            Carbon::parse($expiresAt)->toDateString(),
            Carbon::parse($token->accessToken->expires_at)->toDateString()
        );
    }

    public function test_create_does_not_set_expiry_when_expires_at_is_empty(): void
    {
        $user = User::factory()->create();
        $action = app(CreateNewUserToken::class);

        $token = $action->create($user, ['name' => 'No Expiry', 'expires_at' => '']);

        $this->assertNull($token->accessToken->expires_at);
    }

    public function test_create_does_not_set_expiry_when_expires_at_is_absent(): void
    {
        $user = User::factory()->create();
        $action = app(CreateNewUserToken::class);

        $token = $action->create($user, ['name' => 'No Expiry Key']);

        $this->assertNull($token->accessToken->expires_at);
    }

    public function test_create_grants_wildcard_abilities(): void
    {
        $user = User::factory()->create();
        $action = app(CreateNewUserToken::class);

        $token = $action->create($user, ['name' => 'Wildcard Token']);

        $this->assertTrue($token->accessToken->can('*'));
    }

    public function test_create_associates_token_with_correct_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $action = app(CreateNewUserToken::class);

        $action->create($userA, ['name' => 'User A Token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $userA->id,
            'name' => 'User A Token',
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $userB->id,
            'name' => 'User A Token',
        ]);
    }
}
