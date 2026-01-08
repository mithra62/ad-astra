<?php
namespace Tests\Unit\Actions\User;

use App\Actions\User\UpdateUserPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_updates_user_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($user);

        $action = new UpdateUserPassword();
        $input = [
            'current_password' => 'current-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        $action->update($user, $input);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
