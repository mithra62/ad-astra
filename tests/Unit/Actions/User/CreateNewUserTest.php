<?php
namespace Tests\Unit\Actions\User;

use App\Actions\User\CreateNewUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateNewUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_creates_new_user()
    {
        $action = new CreateNewUser();
        $input = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ];

        $user = $action->create($input);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_create_assigns_roles_if_provided()
    {
        $role = Role::create(['name' => 'admin']);
        $action = new CreateNewUser();
        $input = [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'roles' => ['admin'],
        ];

        $user = $action->create($input);

        $this->assertTrue($user->hasRole('admin'));
    }
}
