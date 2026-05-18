<?php

namespace Tests\Unit\Services;

use App\Models\Entry;
use App\Models\EntryAuthor;
use App\Models\Role;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\User\OauthToken;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    public function test_find_returns_user_when_it_exists(): void
    {
        $user = User::factory()->create();

        $result = $this->service->find($user->id);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
    }

    // -------------------------------------------------------------------------
    // find()
    // -------------------------------------------------------------------------

    public function test_find_returns_null_when_user_does_not_exist(): void
    {
        $result = $this->service->find(999999);

        $this->assertNull($result);
    }

    public function test_get_returns_user_when_it_exists(): void
    {
        $user = User::factory()->create();

        $result = $this->service->get($user->id);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function test_get_throws_when_user_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->get(999999);
    }

    public function test_paginate_returns_length_aware_paginator(): void
    {
        User::factory()->count(5)->create();

        $result = $this->service->paginate(3);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    // -------------------------------------------------------------------------
    // paginate()
    // -------------------------------------------------------------------------

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

    public function test_get_for_dropdown_returns_collection(): void
    {
        User::factory()->count(3)->create();

        $result = $this->service->getForDropdown();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $result);
    }

    // -------------------------------------------------------------------------
    // getForDropdown()
    // -------------------------------------------------------------------------

    public function test_get_for_dropdown_respects_limit(): void
    {
        User::factory()->count(10)->create();

        $result = $this->service->getForDropdown(5);

        $this->assertCount(5, $result);
    }

    public function test_get_for_dropdown_returns_only_id_and_name(): void
    {
        User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $item = $this->service->getForDropdown()->first();

        $this->assertNotNull($item->id);
        $this->assertNotNull($item->name);
        $this->assertNull($item->email);
    }

    public function test_get_for_dropdown_is_ordered_by_name(): void
    {
        User::factory()->create(['name' => 'Zara']);
        User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Mike']);

        $names = $this->service->getForDropdown()->pluck('name')->values()->all();

        $this->assertEquals(['Alice', 'Mike', 'Zara'], $names);
    }

    public function test_get_total_count_returns_correct_count(): void
    {
        User::factory()->count(7)->create();

        $this->assertEquals(7, $this->service->getTotalCount());
    }

    // -------------------------------------------------------------------------
    // getTotalCount()
    // -------------------------------------------------------------------------

    public function test_get_total_count_returns_zero_when_no_users(): void
    {
        $this->assertEquals(0, $this->service->getTotalCount());
    }

    public function test_get_latest_users_returns_collection(): void
    {
        User::factory()->count(3)->create();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Collection::class,
            $this->service->getLatestUsers()
        );
    }

    // -------------------------------------------------------------------------
    // getLatestUsers()
    // -------------------------------------------------------------------------

    public function test_get_latest_users_respects_limit(): void
    {
        User::factory()->count(15)->create();

        $this->assertCount(5, $this->service->getLatestUsers(5));
    }

    public function test_get_latest_users_defaults_to_nine(): void
    {
        User::factory()->count(15)->create();

        $this->assertCount(9, $this->service->getLatestUsers());
    }

    public function test_get_latest_users_ordered_newest_first(): void
    {
        $older = User::factory()->create(['created_at' => now()->subDays(5)]);
        $newer = User::factory()->create(['created_at' => now()->subDay()]);

        $result = $this->service->getLatestUsers(2);

        $this->assertEquals($newer->id, $result->first()->id);
        $this->assertEquals($older->id, $result->last()->id);
    }

    public function test_first_or_create_from_social_creates_new_user_when_not_found(): void
    {
        $result = $this->service->firstOrCreateFromSocial('social@example.com', 'Social User', 'github', '127.0.0.1');

        $this->assertInstanceOf(User::class, $result);
        $this->assertDatabaseHas('users', [
            'email' => 'social@example.com',
            'name' => 'Social User',
        ]);
    }

    // -------------------------------------------------------------------------
    // firstOrCreateFromSocial()
    // -------------------------------------------------------------------------

    public function test_first_or_create_from_social_returns_existing_user_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.com', 'name' => 'Original Name']);

        $result = $this->service->firstOrCreateFromSocial('existing@example.com', 'Different Name', 'github', '127.0.0.1');

        $this->assertEquals($existing->id, $result->id);
        $this->assertEquals('Original Name', $result->name);
    }

    public function test_first_or_create_from_social_does_not_duplicate_user(): void
    {
        $this->service->firstOrCreateFromSocial('once@example.com', 'Once', 'github', '127.0.0.1');
        $this->service->firstOrCreateFromSocial('once@example.com', 'Twice', 'github', '127.0.0.1');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_first_or_create_from_social_sets_a_password_on_creation(): void
    {
        $result = $this->service->firstOrCreateFromSocial('newuser@example.com', 'New User', 'github', '127.0.0.1');

        $this->assertNotNull($result->fresh()->password);
    }

    public function test_create_returns_user_instance(): void
    {
        $result = $this->service->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'Secret1234!',
        ]);

        $this->assertInstanceOf(User::class, $result);
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    public function test_create_persists_user_to_database(): void
    {
        $this->service->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'Secret1234!',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);
    }

    public function test_create_hashes_the_password(): void
    {
        $this->service->create([
            'name' => 'Carol',
            'email' => 'carol@example.com',
            'password' => 'PlainText1!',
        ]);

        $user = User::where('email', 'carol@example.com')->first();
        $this->assertTrue(Hash::check('PlainText1!', $user->password));
        $this->assertNotEquals('PlainText1!', $user->password);
    }

    public function test_create_strips_password_confirmation_from_attributes(): void
    {
        $this->service->create([
            'name' => 'Eve',
            'email' => 'eve@example.com',
            'password' => 'Secret1234!',
            'password_confirmation' => 'Secret1234!',
        ]);

        // If password_confirmation were passed to User::create it would throw
        // a MassAssignmentException / query error — reaching here means it was stripped.
        $this->assertDatabaseHas('users', ['email' => 'eve@example.com']);
    }

    public function test_create_syncs_roles_when_roles_key_is_provided(): void
    {
        $role = Role::factory()->create(['name' => 'editor']);
        $result = $this->service->create([
            'name' => 'Frank',
            'email' => 'frank@example.com',
            'password' => 'Secret1234!',
            'roles' => ['editor'],
        ]);

        $this->assertTrue($result->hasRole('editor'));
    }

    public function test_create_skips_role_sync_when_roles_key_is_absent(): void
    {
        $result = $this->service->create([
            'name' => 'Grace',
            'email' => 'grace@example.com',
            'password' => 'Secret1234!',
        ]);

        $this->assertEmpty($result->roles);
    }

    public function test_create_skips_role_sync_when_roles_array_is_empty(): void
    {
        $result = $this->service->create([
            'name' => 'Hank',
            'email' => 'hank@example.com',
            'password' => 'Secret1234!',
            'roles' => [],
        ]);

        $this->assertEmpty($result->roles);
    }

    public function test_create_processes_fields_key_when_present(): void
    {
        // Passing an empty fields array hits the branch without needing real Field records.
        $result = $this->service->create([
            'name' => 'Iris',
            'email' => 'iris@example.com',
            'password' => 'Secret1234!',
            'fields' => [],
        ]);

        $this->assertInstanceOf(User::class, $result);
    }

    public function test_create_skips_fields_when_key_is_absent(): void
    {
        $result = $this->service->create([
            'name' => 'Jack',
            'email' => 'jack@example.com',
            'password' => 'Secret1234!',
        ]);

        $this->assertInstanceOf(User::class, $result);
    }

    public function test_create_returns_refreshed_model(): void
    {
        $result = $this->service->create([
            'name' => 'Kira',
            'email' => 'kira@example.com',
            'password' => 'Secret1234!',
        ]);

        // A refreshed model has a non-null id
        $this->assertNotNull($result->id);
    }

    public function test_update_returns_user_instance(): void
    {
        $user = User::factory()->create();
        $result = $this->service->update($user, ['name' => 'Updated']);

        $this->assertInstanceOf(User::class, $result);
    }

    // -------------------------------------------------------------------------
    // update()
    // -------------------------------------------------------------------------

    public function test_update_persists_changed_attributes(): void
    {
        $user = User::factory()->create(['name' => 'Before']);

        $this->service->update($user, ['name' => 'After']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'After']);
    }

    public function test_update_skips_model_save_when_only_reserved_keys_provided(): void
    {
        $user = User::factory()->create(['name' => 'Unchanged']);

        // 'password', 'roles', 'fields' are stripped; empty attributes = no update()
        $this->service->update($user, ['roles' => [], 'fields' => []]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Unchanged']);
    }

    public function test_update_syncs_roles_when_roles_key_is_present(): void
    {
        $role = Role::factory()->create(['name' => 'moderator']);
        $user = User::factory()->create();

        $this->service->update($user, ['roles' => ['moderator']]);

        $this->assertTrue($user->fresh()->hasRole('moderator'));
    }

    public function test_update_skips_role_sync_when_roles_key_is_absent(): void
    {
        $role = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        // No 'roles' key — existing roles should be untouched
        $this->service->update($user, ['name' => 'Same']);

        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_update_processes_fields_key_when_present(): void
    {
        $user = User::factory()->create();
        $result = $this->service->update($user, ['fields' => []]);

        $this->assertInstanceOf(User::class, $result);
    }

    public function test_update_skips_fields_when_key_is_absent(): void
    {
        $user = User::factory()->create();
        $result = $this->service->update($user, ['name' => 'NoFields']);

        $this->assertInstanceOf(User::class, $result);
    }

    public function test_update_returns_model_with_updated_values(): void
    {
        $user = User::factory()->create(['name' => 'Before']);
        $result = $this->service->update($user, ['name' => 'After']);

        // refresh() returns $this, so the instance is the same object —
        // what matters is that the returned model reflects the persisted change.
        $this->assertEquals('After', $result->name);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'After']);
    }

    public function test_delete_removes_user_from_database(): void
    {
        $user = User::factory()->create();

        $this->service->delete($user);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function test_delete_returns_true_on_success(): void
    {
        $user = User::factory()->create();
        $result = $this->service->delete($user);

        $this->assertTrue($result);
    }

    public function test_delete_throws_when_user_has_created_entries(): void
    {
        $user = User::factory()->create();
        Entry::factory()->create(['created_by_user_id' => $user->id]);

        $this->expectException(ValidationException::class);

        $this->service->delete($user);
    }

    public function test_delete_does_not_remove_user_when_they_have_created_entries(): void
    {
        $user = User::factory()->create();
        Entry::factory()->create(['created_by_user_id' => $user->id]);

        try {
            $this->service->delete($user);
        } catch (ValidationException) {
            // expected
        }

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_throws_when_user_is_an_entry_author(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->create(['user_id' => $user->id]);

        $this->expectException(ValidationException::class);

        $this->service->delete($user);
    }

    public function test_delete_does_not_remove_user_when_they_are_an_entry_author(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->create(['user_id' => $user->id]);

        try {
            $this->service->delete($user);
        } catch (ValidationException) {
            // expected
        }

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_assign_roles_adds_role_to_user(): void
    {
        Role::factory()->create(['name' => 'writer']);
        $user = User::factory()->create();

        $this->service->assignRoles($user, ['writer']);

        $this->assertTrue($user->hasRole('writer'));
    }

    // -------------------------------------------------------------------------
    // assignRoles()
    // -------------------------------------------------------------------------

    public function test_assign_roles_accepts_a_string_role_name(): void
    {
        Role::factory()->create(['name' => 'viewer']);
        $user = User::factory()->create();

        $this->service->assignRoles($user, 'viewer');

        $this->assertTrue($user->hasRole('viewer'));
    }

    public function test_assign_roles_does_not_remove_existing_roles(): void
    {
        Role::factory()->create(['name' => 'admin']);
        Role::factory()->create(['name' => 'editor']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->service->assignRoles($user, 'editor');

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
    }

    public function test_assign_roles_returns_the_user(): void
    {
        Role::factory()->create(['name' => 'guest']);
        $user = User::factory()->create();
        $result = $this->service->assignRoles($user, 'guest');

        $this->assertSame($user, $result);
    }

    public function test_sync_roles_replaces_existing_roles(): void
    {
        Role::factory()->create(['name' => 'admin']);
        Role::factory()->create(['name' => 'editor']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->service->syncRoles($user, ['editor']);

        $this->assertFalse($user->fresh()->hasRole('admin'));
        $this->assertTrue($user->fresh()->hasRole('editor'));
    }

    // -------------------------------------------------------------------------
    // syncRoles()
    // -------------------------------------------------------------------------

    public function test_sync_roles_removes_all_roles_when_given_empty_array(): void
    {
        Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->service->syncRoles($user, []);

        $this->assertEmpty($user->fresh()->roles);
    }

    public function test_sync_roles_returns_the_user(): void
    {
        $user = User::factory()->create();
        $result = $this->service->syncRoles($user, []);

        $this->assertSame($user, $result);
    }

    public function test_revoke_role_removes_the_specified_role(): void
    {
        Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->service->revokeRole($user, 'admin');

        $this->assertFalse($user->fresh()->hasRole('admin'));
    }

    // -------------------------------------------------------------------------
    // revokeRole()
    // -------------------------------------------------------------------------

    public function test_revoke_role_leaves_other_roles_intact(): void
    {
        Role::factory()->create(['name' => 'admin']);
        Role::factory()->create(['name' => 'editor']);
        $user = User::factory()->create();
        $user->assignRole(['admin', 'editor']);

        $this->service->revokeRole($user, 'editor');

        $this->assertTrue($user->fresh()->hasRole('admin'));
        $this->assertFalse($user->fresh()->hasRole('editor'));
    }

    public function test_revoke_role_returns_the_user(): void
    {
        Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $result = $this->service->revokeRole($user, 'admin');

        $this->assertSame($user, $result);
    }

    public function test_set_password_persists_new_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass1!')]);

        $this->service->setPassword($user, 'NewPass1!');

        $this->assertTrue(Hash::check('NewPass1!', $user->fresh()->password));
    }

    // -------------------------------------------------------------------------
    // setPassword()
    // -------------------------------------------------------------------------

    public function test_set_password_invalidates_old_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass1!')]);

        $this->service->setPassword($user, 'NewPass1!');

        $this->assertFalse(Hash::check('OldPass1!', $user->fresh()->password));
    }

    public function test_set_password_stores_a_hashed_value_not_plain_text(): void
    {
        $user = User::factory()->create();

        $this->service->setPassword($user, 'PlainText1!');

        $this->assertNotEquals('PlainText1!', $user->fresh()->password);
    }

    public function test_create_token_returns_new_access_token(): void
    {
        $user = User::factory()->create();

        $result = $this->service->createToken($user, 'My Token');

        $this->assertInstanceOf(NewAccessToken::class, $result);
    }

    // -------------------------------------------------------------------------
    // createToken()
    // -------------------------------------------------------------------------

    public function test_create_token_persists_to_database(): void
    {
        $user = User::factory()->create();

        $this->service->createToken($user, 'Stored Token');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Stored Token',
        ]);
    }

    public function test_create_token_with_expiry(): void
    {
        $user = User::factory()->create();
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

    public function test_create_token_uses_given_abilities(): void
    {
        $user = User::factory()->create();
        $token = $this->service->createToken($user, 'Limited', ['read']);

        $this->assertTrue($token->accessToken->can('read'));
        $this->assertFalse($token->accessToken->can('write'));
    }

    public function test_get_token_returns_token_when_found(): void
    {
        $user = User::factory()->create();
        $token = $this->service->createToken($user, 'Findable Token');

        $result = $this->service->getToken($user, $token->accessToken->id);

        $this->assertInstanceOf(PersonalAccessToken::class, $result);
        $this->assertEquals($token->accessToken->id, $result->id);
    }

    // -------------------------------------------------------------------------
    // getToken()
    // -------------------------------------------------------------------------

    public function test_get_token_returns_null_when_not_found(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->service->getToken($user, 999999));
    }

    public function test_get_token_does_not_return_token_belonging_to_another_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $token = $this->service->createToken($userA, 'User A Token');

        $this->assertNull($this->service->getToken($userB, $token->accessToken->id));
    }

    public function test_update_token_changes_token_name(): void
    {
        $user = User::factory()->create();
        $token = $this->service->createToken($user, 'Old Name');
        $updated = $this->service->updateToken($user, $token->accessToken->id, ['name' => 'New Name']);

        $this->assertInstanceOf(PersonalAccessToken::class, $updated);
        $this->assertEquals('New Name', $updated->name);
    }

    // -------------------------------------------------------------------------
    // updateToken()
    // -------------------------------------------------------------------------

    public function test_update_token_persists_change_to_database(): void
    {
        $user = User::factory()->create();
        $token = $this->service->createToken($user, 'Old Name');

        $this->service->updateToken($user, $token->accessToken->id, ['name' => 'Persisted']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->accessToken->id,
            'name' => 'Persisted',
        ]);
    }

    public function test_update_token_returns_null_when_token_not_found(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->service->updateToken($user, 999999, ['name' => 'Ghost']));
    }

    public function test_revoke_token_removes_token_from_database(): void
    {
        $user = User::factory()->create();
        $token = $this->service->createToken($user, 'To Delete');

        $this->service->revokeToken($user, $token->accessToken->id);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    // -------------------------------------------------------------------------
    // revokeToken()
    // -------------------------------------------------------------------------

    public function test_revoke_token_returns_true_on_success(): void
    {
        $user = User::factory()->create();
        $token = $this->service->createToken($user, 'Delete Me');

        $this->assertTrue($this->service->revokeToken($user, $token->accessToken->id));
    }

    public function test_revoke_token_returns_false_when_not_found(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->revokeToken($user, 999999));
    }

    public function test_enable_two_factor_returns_array_with_required_keys(): void
    {
        $user = User::factory()->create();
        $result = $this->service->enableTwoFactor($user);

        $this->assertArrayHasKey('qr_code_svg', $result);
        $this->assertArrayHasKey('secret', $result);
    }

    // -------------------------------------------------------------------------
    // enableTwoFactor()
    // -------------------------------------------------------------------------

    public function test_enable_two_factor_sets_secret_on_user(): void
    {
        $user = User::factory()->create();

        $this->service->enableTwoFactor($user);

        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_enable_two_factor_sets_recovery_codes_on_user(): void
    {
        $user = User::factory()->create();

        $this->service->enableTwoFactor($user);

        $this->assertNotNull($user->fresh()->two_factor_recovery_codes);
    }

    public function test_enable_two_factor_qr_code_is_a_non_empty_string(): void
    {
        $user = User::factory()->create();
        $result = $this->service->enableTwoFactor($user);

        $this->assertIsString($result['qr_code_svg']);
        $this->assertNotEmpty($result['qr_code_svg']);
    }

    public function test_enable_two_factor_secret_is_a_non_empty_string(): void
    {
        $user = User::factory()->create();
        $result = $this->service->enableTwoFactor($user);

        $this->assertIsString($result['secret']);
        $this->assertNotEmpty($result['secret']);
    }

    public function test_confirm_two_factor_delegates_to_fortify_action(): void
    {
        $user = User::factory()->create();

        // Bind a mock into the container so we don't need a real TOTP code
        $this->app->bind(ConfirmTwoFactorAuthentication::class, function () use ($user) {
            $mock = $this->createMock(ConfirmTwoFactorAuthentication::class);
            $mock->expects($this->once())
                ->method('__invoke')
                ->with($user, '123456');
            return $mock;
        });

        $this->service->confirmTwoFactor($user, '123456');
    }

    // -------------------------------------------------------------------------
    // confirmTwoFactor()
    // -------------------------------------------------------------------------

    public function test_disable_two_factor_clears_two_factor_secret(): void
    {
        $user = User::factory()->create();
        $this->service->enableTwoFactor($user);

        $this->service->disableTwoFactor($user);

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    // -------------------------------------------------------------------------
    // disableTwoFactor()
    // -------------------------------------------------------------------------

    public function test_disable_two_factor_clears_recovery_codes(): void
    {
        $user = User::factory()->create();
        $this->service->enableTwoFactor($user);

        $this->service->disableTwoFactor($user);

        $this->assertNull($user->fresh()->two_factor_recovery_codes);
    }

    public function test_has_two_factor_returns_false_when_not_confirmed(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->hasTwoFactor($user));
    }

    // -------------------------------------------------------------------------
    // hasTwoFactor()
    // -------------------------------------------------------------------------

    public function test_has_two_factor_returns_true_when_confirmed_at_is_set(): void
    {
        $user = User::factory()->create([
            'two_factor_confirmed_at' => now(),
        ]);

        $this->assertTrue($this->service->hasTwoFactor($user));
    }

    public function test_get_recovery_codes_returns_empty_array_when_no_2fa(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], $this->service->getRecoveryCodes($user));
    }

    // -------------------------------------------------------------------------
    // getRecoveryCodes()
    // -------------------------------------------------------------------------

    public function test_get_recovery_codes_returns_array_after_enabling_2fa(): void
    {
        $user = User::factory()->create();
        $this->service->enableTwoFactor($user);
        $user->refresh();

        $codes = $this->service->getRecoveryCodes($user);

        $this->assertIsArray($codes);
        $this->assertNotEmpty($codes);
    }

    public function test_get_recovery_codes_returns_eight_codes(): void
    {
        $user = User::factory()->create();
        $this->service->enableTwoFactor($user);
        $user->refresh();

        $codes = $this->service->getRecoveryCodes($user);

        $this->assertCount(8, $codes);
    }

    public function test_regenerate_recovery_codes_returns_an_array_of_codes(): void
    {
        $user = User::factory()->create();
        $this->service->enableTwoFactor($user);

        $codes = $this->service->regenerateRecoveryCodes($user);

        $this->assertIsArray($codes);
        $this->assertCount(8, $codes);
    }

    // -------------------------------------------------------------------------
    // regenerateRecoveryCodes()
    // -------------------------------------------------------------------------

    public function test_regenerate_recovery_codes_produces_new_codes(): void
    {
        $user = User::factory()->create();
        $this->service->enableTwoFactor($user);
        $user->refresh();

        $original = $this->service->getRecoveryCodes($user);
        $regenerated = $this->service->regenerateRecoveryCodes($user);

        // Extremely unlikely to produce the same 8 random codes
        $this->assertNotEquals($original, $regenerated);
    }

    public function test_upsert_oauth_token_returns_oauth_token_instance(): void
    {
        $user = User::factory()->create();
        $result = $this->service->upsertOauthToken($user, 'google', [
            'access_token' => 'tok_abc',
        ]);

        $this->assertInstanceOf(OauthToken::class, $result);
    }

    // -------------------------------------------------------------------------
    // upsertOauthToken()
    // -------------------------------------------------------------------------

    public function test_upsert_oauth_token_persists_to_database(): void
    {
        $user = User::factory()->create();

        $this->service->upsertOauthToken($user, 'github', [
            'access_token' => 'gh_token',
        ]);

        $this->assertDatabaseHas('user_oauth_tokens', [
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'gh_token',
        ]);
    }

    public function test_upsert_oauth_token_revokes_existing_active_token_for_same_provider(): void
    {
        $user = User::factory()->create();
        $older = OauthToken::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'revoked_at' => null,
        ]);

        $this->service->upsertOauthToken($user, 'google', ['access_token' => 'new_tok']);

        $this->assertNotNull($older->fresh()->revoked_at);
    }

    public function test_upsert_oauth_token_does_not_revoke_tokens_for_different_provider(): void
    {
        $user = User::factory()->create();
        $github = OauthToken::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'revoked_at' => null,
        ]);

        $this->service->upsertOauthToken($user, 'google', ['access_token' => 'new_tok']);

        $this->assertNull($github->fresh()->revoked_at);
    }

    public function test_get_active_oauth_token_returns_token_when_active(): void
    {
        $user = User::factory()->create();
        OauthToken::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'revoked_at' => null,
            'expires_at' => now()->addHour(),
        ]);

        $result = $this->service->getActiveOauthToken($user, 'google');

        $this->assertInstanceOf(OauthToken::class, $result);
    }

    // -------------------------------------------------------------------------
    // getActiveOauthToken()
    // -------------------------------------------------------------------------

    public function test_get_active_oauth_token_returns_null_when_none_exists(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->service->getActiveOauthToken($user, 'google'));
    }

    public function test_get_active_oauth_token_returns_null_when_token_is_revoked(): void
    {
        $user = User::factory()->create();
        OauthToken::factory()->revoked()->create([
            'user_id' => $user->id,
            'provider' => 'google',
        ]);

        $this->assertNull($this->service->getActiveOauthToken($user, 'google'));
    }

    public function test_revoke_oauth_token_sets_revoked_at(): void
    {
        $user = User::factory()->create();
        $token = OauthToken::factory()->create(['user_id' => $user->id, 'revoked_at' => null]);

        $this->service->revokeOauthToken($token);

        $this->assertNotNull($token->fresh()->revoked_at);
    }

    // -------------------------------------------------------------------------
    // revokeOauthToken()
    // -------------------------------------------------------------------------

    public function test_revoke_oauth_token_makes_token_inactive(): void
    {
        $user = User::factory()->create();
        $token = OauthToken::factory()->create(['user_id' => $user->id, 'revoked_at' => null]);

        $this->service->revokeOauthToken($token);

        $this->assertFalse($token->fresh()->isActive());
    }

    public function test_revoke_all_oauth_tokens_revokes_all_active_tokens(): void
    {
        $user = User::factory()->create();
        $t1 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);
        $t2 = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'github', 'revoked_at' => null]);

        $this->service->revokeAllOauthTokens($user);

        $this->assertNotNull($t1->fresh()->revoked_at);
        $this->assertNotNull($t2->fresh()->revoked_at);
    }

    // -------------------------------------------------------------------------
    // revokeAllOauthTokens()
    // -------------------------------------------------------------------------

    public function test_revoke_all_oauth_tokens_with_provider_filter_only_revokes_that_provider(): void
    {
        $user = User::factory()->create();
        $google = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);
        $github = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'github', 'revoked_at' => null]);

        $this->service->revokeAllOauthTokens($user, 'google');

        $this->assertNotNull($google->fresh()->revoked_at);
        $this->assertNull($github->fresh()->revoked_at);
    }

    public function test_revoke_all_oauth_tokens_leaves_already_revoked_tokens_untouched(): void
    {
        $user = User::factory()->create();
        $past = now()->subHour();
        $token = OauthToken::factory()->revoked()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'revoked_at' => $past,
        ]);

        $this->service->revokeAllOauthTokens($user, 'google');

        // The revoked_at timestamp should not have been touched again
        $this->assertEquals(
            $past->toDateTimeString(),
            $token->fresh()->revoked_at->toDateTimeString()
        );
    }

    public function test_list_oauth_tokens_returns_only_active_tokens(): void
    {
        $user = User::factory()->create();
        $active = OauthToken::factory()->create(['user_id' => $user->id, 'revoked_at' => null]);
        $revoked = OauthToken::factory()->revoked()->create(['user_id' => $user->id]);

        $result = $this->service->listOauthTokens($user);

        $this->assertTrue($result->contains('id', $active->id));
        $this->assertFalse($result->contains('id', $revoked->id));
    }

    // -------------------------------------------------------------------------
    // listOauthTokens()
    // -------------------------------------------------------------------------

    public function test_list_oauth_tokens_with_provider_filter_returns_only_that_providers_tokens(): void
    {
        $user = User::factory()->create();
        $google = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);
        $github = OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'github', 'revoked_at' => null]);

        $result = $this->service->listOauthTokens($user, 'google');

        $this->assertTrue($result->contains('id', $google->id));
        $this->assertFalse($result->contains('id', $github->id));
    }

    public function test_list_oauth_tokens_returns_results_ordered_newest_first(): void
    {
        $user = User::factory()->create();
        $older = OauthToken::factory()->create(['user_id' => $user->id, 'revoked_at' => null, 'created_at' => now()->subDays(2)]);
        $newer = OauthToken::factory()->create(['user_id' => $user->id, 'revoked_at' => null, 'created_at' => now()]);

        $result = $this->service->listOauthTokens($user);

        $this->assertEquals($newer->id, $result->first()->id);
        $this->assertEquals($older->id, $result->last()->id);
    }

    public function test_list_oauth_tokens_without_filter_returns_all_providers(): void
    {
        $user = User::factory()->create();
        OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'google', 'revoked_at' => null]);
        OauthToken::factory()->create(['user_id' => $user->id, 'provider' => 'github', 'revoked_at' => null]);

        $result = $this->service->listOauthTokens($user);

        $this->assertCount(2, $result);
    }

    // -------------------------------------------------------------------------
    // create() — author eligibility
    // -------------------------------------------------------------------------

    public function test_create_promotes_user_to_author_when_is_author_is_true(): void
    {
        $result = $this->service->create([
            'name'      => 'Author',
            'email'     => 'author@example.com',
            'password'  => 'Secret1234!',
            'is_author' => true,
        ]);

        $this->assertDatabaseHas('entry_authors', ['user_id' => $result->id, 'status' => 'active']);
    }

    public function test_create_does_not_create_entry_author_when_is_author_is_false(): void
    {
        $result = $this->service->create([
            'name'      => 'Non Author',
            'email'     => 'nonauthor@example.com',
            'password'  => 'Secret1234!',
            'is_author' => false,
        ]);

        $this->assertDatabaseMissing('entry_authors', ['user_id' => $result->id]);
    }

    public function test_create_skips_author_sync_when_is_author_key_is_absent(): void
    {
        $result = $this->service->create([
            'name'     => 'Skippy',
            'email'    => 'skippy@example.com',
            'password' => 'Secret1234!',
        ]);

        $this->assertDatabaseMissing('entry_authors', ['user_id' => $result->id]);
    }

    public function test_create_sets_author_display_name_when_provided(): void
    {
        $result = $this->service->create([
            'name'                => 'Writer',
            'email'               => 'writer@example.com',
            'password'            => 'Secret1234!',
            'is_author'           => true,
            'author_display_name' => 'The Wordsmith',
        ]);

        $this->assertDatabaseHas('entry_authors', [
            'user_id'      => $result->id,
            'display_name' => 'The Wordsmith',
        ]);
    }

    public function test_create_strips_is_author_from_stored_user_attributes(): void
    {
        // If is_author were passed to User::create() it would throw a
        // MassAssignmentException — reaching this assertion means it was stripped.
        $result = $this->service->create([
            'name'      => 'Safe',
            'email'     => 'safe@example.com',
            'password'  => 'Secret1234!',
            'is_author' => true,
        ]);

        $this->assertDatabaseHas('users', ['email' => 'safe@example.com']);
    }

    // -------------------------------------------------------------------------
    // update() — author eligibility
    // -------------------------------------------------------------------------

    public function test_update_promotes_user_to_author_when_is_author_is_true(): void
    {
        $user = User::factory()->create();

        $this->service->update($user, ['is_author' => true]);

        $this->assertDatabaseHas('entry_authors', ['user_id' => $user->id, 'status' => 'active']);
    }

    public function test_update_demotes_user_when_is_author_is_false(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->service->update($user, ['is_author' => false]);

        $this->assertDatabaseHas('entry_authors', ['user_id' => $user->id, 'status' => 'disabled']);
    }

    public function test_update_skips_author_sync_when_is_author_key_is_absent(): void
    {
        $user = User::factory()->create();
        $ea = EntryAuthor::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        // No 'is_author' key — eligibility should be left entirely untouched
        $this->service->update($user, ['name' => 'No Change']);

        $this->assertDatabaseHas('entry_authors', ['id' => $ea->id, 'status' => 'active']);
    }

    public function test_update_passes_author_display_name_to_service(): void
    {
        $user = User::factory()->create();

        $this->service->update($user, [
            'is_author'           => true,
            'author_display_name' => 'New Alias',
        ]);

        $this->assertDatabaseHas('entry_authors', [
            'user_id'      => $user->id,
            'display_name' => 'New Alias',
        ]);
    }

    public function test_update_strips_is_author_from_stored_user_attributes(): void
    {
        $user = User::factory()->create(['name' => 'Before']);

        // If is_author were passed to User::update() it would attempt to set
        // an unknown column — reaching this assertion means it was stripped.
        $this->service->update($user, ['name' => 'After', 'is_author' => true]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'After']);
    }

    public function test_update_can_promote_a_previously_disabled_author(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->disabled()->create(['user_id' => $user->id]);

        $this->service->update($user, ['is_author' => true]);

        $this->assertDatabaseHas('entry_authors', ['user_id' => $user->id, 'status' => 'active']);
        $this->assertDatabaseCount('entry_authors', 1);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserService::class);
    }
}
