<?php
namespace Tests\Unit\Actions\User;

use App\Actions\User\ResetUserPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_updates_user_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $action = new ResetUserPassword();
        $input = [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        $action->reset($user, $input);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
