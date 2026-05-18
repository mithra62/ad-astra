<?php

namespace Tests\Unit\Actions\User;

use App\Actions\User\CreateNewUser;
use App\Actions\User\ResetUserPassword;
use App\Actions\User\UpdateUserPassword;
use App\Actions\User\UpdateUserProfileInformation;
use App\Facades\Users as UsersFacade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CreateNewUser
    // -------------------------------------------------------------------------

    public function test_create_delegates_to_users_facade(): void
    {
        $user = User::factory()->make();
        UsersFacade::shouldReceive('create')
            ->once()
            ->with(['name' => 'Alice', 'email' => 'alice@example.com'])
            ->andReturn($user);

        $action = app(CreateNewUser::class);
        $result = $action->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertSame($user, $result);
    }

    public function test_create_returns_user_instance(): void
    {
        $user = User::factory()->make();
        UsersFacade::shouldReceive('create')->once()->andReturn($user);

        $action = app(CreateNewUser::class);
        $result = $action->create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $this->assertInstanceOf(User::class, $result);
    }

    public function test_create_passes_full_input_to_facade(): void
    {
        $input = ['name' => 'Carol', 'email' => 'carol@example.com', 'password' => 'secret'];
        $user = User::factory()->make();

        UsersFacade::shouldReceive('create')
            ->once()
            ->with($input)
            ->andReturn($user);

        $action = app(CreateNewUser::class);
        $action->create($input);
    }

    // -------------------------------------------------------------------------
    // ResetUserPassword
    // -------------------------------------------------------------------------

    public function test_reset_updates_user_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $action = app(ResetUserPassword::class);

        $action->reset($user, [
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $this->assertTrue(Hash::check('NewPassword1!', $user->fresh()->password));
    }

    public function test_reset_fails_validation_when_passwords_do_not_match(): void
    {
        $user = User::factory()->create();
        $action = app(ResetUserPassword::class);

        $this->expectException(ValidationException::class);

        $action->reset($user, [
            'password' => 'NewPassword1!',
            'password_confirmation' => 'DifferentPassword1!',
        ]);
    }

    public function test_reset_fails_validation_when_password_is_missing(): void
    {
        $user = User::factory()->create();
        $action = app(ResetUserPassword::class);

        $this->expectException(ValidationException::class);

        $action->reset($user, ['password' => '', 'password_confirmation' => '']);
    }

    public function test_reset_hashes_new_password(): void
    {
        $user = User::factory()->create();
        $action = app(ResetUserPassword::class);

        $action->reset($user, [
            'password' => 'HashedPassword1!',
            'password_confirmation' => 'HashedPassword1!',
        ]);

        $this->assertFalse($user->fresh()->password === 'HashedPassword1!');
        $this->assertTrue(Hash::check('HashedPassword1!', $user->fresh()->password));
    }

    // -------------------------------------------------------------------------
    // UpdateUserPassword
    // -------------------------------------------------------------------------

    public function test_update_password_updates_user_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CurrentPass1!')]);
        $this->actingAs($user);
        $action = app(UpdateUserPassword::class);

        $action->update($user, [
            'current_password' => 'CurrentPass1!',
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ]);

        $this->assertTrue(Hash::check('NewSecurePass1!', $user->fresh()->password));
    }

    public function _test_update_password_fails_when_current_password_is_wrong(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CorrectPass1!')]);
        $this->actingAs($user);
        $action = app(UpdateUserPassword::class);

        $this->expectException(ValidationException::class);

        $action->update($user, [
            'current_password' => 'WrongPass1!',
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ]);
    }

    public function _test_update_password_fails_when_new_passwords_do_not_match(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CurrentPass1!')]);
        $this->actingAs($user);
        $action = app(UpdateUserPassword::class);

        $this->expectException(ValidationException::class);

        $action->update($user, [
            'current_password' => 'CurrentPass1!',
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'DifferentPass1!',
        ]);
    }

    public function test_update_password_hashes_new_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CurrentPass1!')]);
        $this->actingAs($user);
        $action = app(UpdateUserPassword::class);

        $action->update($user, [
            'current_password' => 'CurrentPass1!',
            'password' => 'FreshNewPass1!',
            'password_confirmation' => 'FreshNewPass1!',
        ]);

        $this->assertFalse($user->fresh()->password === 'FreshNewPass1!');
        $this->assertTrue(Hash::check('FreshNewPass1!', $user->fresh()->password));
    }

    // -------------------------------------------------------------------------
    // UpdateUserProfileInformation
    // -------------------------------------------------------------------------

    public function test_update_profile_delegates_to_users_facade(): void
    {
        $user = User::factory()->create();
        $updated = User::factory()->make(['name' => 'Updated Name']);

        UsersFacade::shouldReceive('update')
            ->once()
            ->with($user, ['name' => 'Updated Name'])
            ->andReturn($updated);

        $action = app(UpdateUserProfileInformation::class);
        $result = $action->update($user, ['name' => 'Updated Name']);

        $this->assertSame($updated, $result);
    }

    public function test_update_profile_returns_user_instance(): void
    {
        $user = User::factory()->create();
        $updated = User::factory()->make();

        UsersFacade::shouldReceive('update')->once()->andReturn($updated);

        $action = app(UpdateUserProfileInformation::class);
        $result = $action->update($user, []);

        $this->assertInstanceOf(User::class, $result);
    }

    public function test_update_profile_passes_user_and_input_to_facade(): void
    {
        $user = User::factory()->create();
        $input = ['name' => 'New Name', 'email' => 'new@example.com'];

        UsersFacade::shouldReceive('update')
            ->once()
            ->with($user, $input)
            ->andReturn($user);

        $action = app(UpdateUserProfileInformation::class);
        $action->update($user, $input);
    }
}
