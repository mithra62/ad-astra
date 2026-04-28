<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserService::class);
    }

    // -------------------------------------------------------------------------
    // find()
    // -------------------------------------------------------------------------

    public function test_find_returns_user_when_it_exists(): void
    {
        $user = User::factory()->create();

        $result = $this->service->find($user->id);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
    }

    public function test_find_returns_null_when_user_does_not_exist(): void
    {
        $result = $this->service->find(999999);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function test_get_returns_user_when_it_exists(): void
    {
        $user = User::factory()->create();

        $result = $this->service->get($user->id);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
    }

    public function test_get_throws_when_user_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->get(999999);
    }

    // -------------------------------------------------------------------------
    // paginate()
    // -------------------------------------------------------------------------

    public function test_paginate_returns_length_aware_paginator(): void
    {
        User::factory()->count(5)->create();

        $result = $this->service->paginate(3);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    public function test_paginate_respects_per_page_argument(): void
    {
        User::factory()->count(10)->create();

        $result = $this->service->paginate(4);

        $this->assertCount(4, $result->items());
    }

    public function test_paginate_eager_loads_given_relations(): void
    {
        User::factory()->create();

        $result = $this->service->paginate(20, ['roles']);

        $this->assertTrue($result->first()->relationLoaded('roles'));
    }

    public function test_paginate_defaults_to_roles_relation(): void
    {
        User::factory()->create();

        $result = $this->service->paginate();

        $this->assertTrue($result->first()->relationLoaded('roles'));
    }

    // -------------------------------------------------------------------------
    // getForDropdown()
    // -------------------------------------------------------------------------

    public function test_get_for_dropdown_returns_collection(): void
    {
        User::factory()->count(3)->create();

        $result = $this->service->getForDropdown();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $result);
    }

    public function test_get_for_dropdown_respects_limit(): void
    {
        User::factory()->count(10)->create();

        $result = $this->service->getForDropdown(5);

        $this->assertCount(5, $result);
    }

    public function test_get_for_dropdown_returns_only_id_and_name(): void
    {
        User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $result = $this->service->getForDropdown();
        $item   = $result->first();

        $this->assertNotNull($item->id);
        $this->assertNotNull($item->name);
        $this->assertNull($item->email);
    }

    public function test_get_for_dropdown_is_ordered_by_name(): void
    {
        User::factory()->create(['name' => 'Zara']);
        User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Mike']);

        $result = $this->service->getForDropdown();
        $names  = $result->pluck('name')->values()->all();

        $this->assertEquals(['Alice', 'Mike', 'Zara'], $names);
    }

    // -------------------------------------------------------------------------
    // getTotalCount()
    // -------------------------------------------------------------------------

    public function test_get_total_count_returns_correct_count(): void
    {
        User::factory()->count(7)->create();

        $result = $this->service->getTotalCount();

        $this->assertEquals(7, $result);
    }

    public function test_get_total_count_returns_zero_when_no_users(): void
    {
        $result = $this->service->getTotalCount();

        $this->assertEquals(0, $result);
    }

    // -------------------------------------------------------------------------
    // getLatestUsers()
    // -------------------------------------------------------------------------

    public function test_get_latest_users_returns_collection(): void
    {
        User::factory()->count(3)->create();

        $result = $this->service->getLatestUsers();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $result);
    }

    public function test_get_latest_users_respects_limit(): void
    {
        User::factory()->count(15)->create();

        $result = $this->service->getLatestUsers(5);

        $this->assertCount(5, $result);
    }

    public function test_get_latest_users_defaults_to_nine(): void
    {
        User::factory()->count(15)->create();

        $result = $this->service->getLatestUsers();

        $this->assertCount(9, $result);
    }

    public function test_get_latest_users_ordered_newest_first(): void
    {
        $older = User::factory()->create(['created_at' => now()->subDays(5)]);
        $newer = User::factory()->create(['created_at' => now()->subDay()]);

        $result = $this->service->getLatestUsers(2);

        $this->assertEquals($newer->id, $result->first()->id);
        $this->assertEquals($older->id, $result->last()->id);
    }

    // -------------------------------------------------------------------------
    // firstOrCreateFromSocial()
    // -------------------------------------------------------------------------

    public function test_first_or_create_from_social_creates_new_user_when_not_found(): void
    {
        $result = $this->service->firstOrCreateFromSocial('social@example.com', 'Social User');

        $this->assertInstanceOf(User::class, $result);
        $this->assertDatabaseHas('users', [
            'email' => 'social@example.com',
            'name'  => 'Social User',
        ]);
    }

    public function test_first_or_create_from_social_returns_existing_user_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.com', 'name' => 'Original Name']);

        $result = $this->service->firstOrCreateFromSocial('existing@example.com', 'Different Name');

        $this->assertEquals($existing->id, $result->id);
        $this->assertEquals('Original Name', $result->name);
    }

    public function test_first_or_create_from_social_does_not_duplicate_user(): void
    {
        $this->service->firstOrCreateFromSocial('once@example.com', 'Once');
        $this->service->firstOrCreateFromSocial('once@example.com', 'Twice');

        $this->assertDatabaseCount('users', 1);
    }

    // -------------------------------------------------------------------------
    // createToken()
    // -------------------------------------------------------------------------

    public function test_create_token_returns_new_access_token(): void
    {
        $user = User::factory()->create();

        $result = $this->service->createToken($user, 'My Token');

        $this->assertInstanceOf(NewAccessToken::class, $result);
    }

    public function test_create_token_persists_to_database(): void
    {
        $user = User::factory()->create();

        $this->service->createToken($user, 'Stored Token');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name'         => 'Stored Token',
        ]);
    }

    public function test_create_token_with_expiry(): void
    {
        $user      = User::factory()->create();
        $expiresAt = Carbon::now()->addDays(30);

        $token = $this->service->createToken($user, 'Expiring Token', ['*'], $expiresAt);

        $this->assertNotNull($token->accessToken->expires_at);
    }

    public function test_create_token_without_expiry_is_null(): void
    {
        $user = User::factory()->create();

        $token = $this->service->createToken($user, 'No Expiry');

        $this->assertNull($token->accessToken->expires_at);
    }

    // -------------------------------------------------------------------------
    // getToken()
    // -------------------------------------------------------------------------

    public function test_get_token_returns_token_when_found(): void
    {
        $user  = User::factory()->create();
        $token = $this->service->createToken($user, 'Findable Token');

        $result = $this->service->getToken($user, $token->accessToken->id);

        $this->assertInstanceOf(PersonalAccessToken::class, $result);
        $this->assertEquals($token->accessToken->id, $result->id);
    }

    public function test_get_token_returns_null_when_not_found(): void
    {
        $user = User::factory()->create();

        $result = $this->service->getToken($user, 999999);

        $this->assertNull($result);
    }

    public function test_get_token_does_not_return_token_belonging_to_another_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $token = $this->service->createToken($userA, 'User A Token');

        $result = $this->service->getToken($userB, $token->accessToken->id);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // updateToken()
    // -------------------------------------------------------------------------

    public function test_update_token_changes_token_name(): void
    {
        $user  = User::factory()->create();
        $token = $this->service->createToken($user, 'Old Name');

        $updated = $this->service->updateToken($user, $token->accessToken->id, ['name' => 'New Name']);

        $this->assertInstanceOf(PersonalAccessToken::class, $updated);
        $this->assertEquals('New Name', $updated->name);
    }

    public function test_update_token_returns_null_when_token_not_found(): void
    {
        $user = User::factory()->create();

        $result = $this->service->updateToken($user, 999999, ['name' => 'Ghost']);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // revokeToken()
    // -------------------------------------------------------------------------

    public function test_revoke_token_removes_token_from_database(): void
    {
        $user  = User::factory()->create();
        $token = $this->service->createToken($user, 'To Delete');

        $this->service->revokeToken($user, $token->accessToken->id);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_revoke_token_returns_true_on_success(): void
    {
        $user  = User::factory()->create();
        $token = $this->service->createToken($user, 'Delete Me');

        $result = $this->service->revokeToken($user, $token->accessToken->id);

        $this->assertTrue($result);
    }

    public function test_revoke_token_returns_false_when_not_found(): void
    {
        $user = User::factory()->create();

        $result = $this->service->revokeToken($user, 999999);

        $this->assertFalse($result);
    }
}
