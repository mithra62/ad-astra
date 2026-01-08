<?php
namespace Tests\Unit\Actions\User\Token;

use App\Actions\User\Token\CreateNewUserToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\NewAccessToken;

class CreateNewUserTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_generates_new_token()
    {
        $user = User::factory()->create();
        $action = new CreateNewUserToken();
        $input = [
            'name' => 'Test Token',
        ];

        $token = $action->create($user, $input);

        $this->assertInstanceOf(NewAccessToken::class, $token);
        $this->assertEquals('Test Token', $token->accessToken->name);
        $this->assertCount(1, $user->tokens);
    }
}
