<?php
namespace Tests\Unit\Actions\User;

use App\Actions\User\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateUserProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_updates_user_profile_information()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $action = new UpdateUserProfileInformation();
        $input = [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ];

        $action->update($user, $input);

        $this->assertEquals('New Name', $user->fresh()->name);
        $this->assertEquals('new@example.com', $user->fresh()->email);
    }
}
