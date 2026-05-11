<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use Tests\TestCase;

class SocialLoginCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function mockSocialite(string $email, string $name): void
    {
        $socialUser = Mockery::mock();
        $socialUser->shouldReceive('getEmail')->andReturn($email);
        $socialUser->shouldReceive('getName')->andReturn($name);

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->andReturn($driver);
    }

    // -------------------------------------------------------------------------
    // Redirect behavior
    // -------------------------------------------------------------------------

    public function test_successful_login_redirects_to_intended_url(): void
    {
        User::factory()->active()->create(['email' => 'user@example.com']);
        $this->mockSocialite('user@example.com', 'Test User');

        $this->withSession(['url.intended' => '/admin/dashboard'])
            ->get(route('social.login.callback', ['provider' => 'google']))
            ->assertRedirect('/admin/dashboard');
    }

    public function test_successful_login_redirects_to_root_when_no_intended_url(): void
    {
        User::factory()->active()->create(['email' => 'user@example.com']);
        $this->mockSocialite('user@example.com', 'Test User');

        $this->get(route('social.login.callback', ['provider' => 'google']))
            ->assertRedirect('/');
    }

    public function test_successful_login_authenticates_the_user(): void
    {
        $user = User::factory()->active()->create(['email' => 'user@example.com']);
        $this->mockSocialite('user@example.com', 'Test User');

        $this->get(route('social.login.callback', ['provider' => 'google']));

        $this->assertAuthenticatedAs($user);
    }

    // -------------------------------------------------------------------------
    // Access denial
    // -------------------------------------------------------------------------

    public function test_inactive_user_is_redirected_to_login_with_error(): void
    {
        User::factory()->inactive()->create(['email' => 'user@example.com']);
        $this->mockSocialite('user@example.com', 'Test User');

        $this->get(route('social.login.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_banned_user_is_redirected_to_login_with_error(): void
    {
        User::factory()->banned()->create(['email' => 'user@example.com']);
        $this->mockSocialite('user@example.com', 'Test User');

        $this->get(route('social.login.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_blocked_user_is_not_authenticated(): void
    {
        User::factory()->inactive()->create(['email' => 'user@example.com']);
        $this->mockSocialite('user@example.com', 'Test User');

        $this->get(route('social.login.callback', ['provider' => 'google']));

        $this->assertGuest();
    }

    // -------------------------------------------------------------------------
    // InvalidStateException
    // -------------------------------------------------------------------------

    public function test_invalid_state_exception_redirects_to_login(): void
    {
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andThrow(new InvalidStateException());
        Socialite::shouldReceive('driver')->andReturn($driver);

        $this->get(route('social.login.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth');
    }
}
