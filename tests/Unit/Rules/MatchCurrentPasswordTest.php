<?php

namespace Tests\Unit\Rules;

use App\Models\User;
use App\Rules\MatchCurrentPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MatchCurrentPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_when_password_matches(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        Auth::login($user);

        $rule = new MatchCurrentPassword();
        $passed = true;

        $rule->validate('password', 'correct-password', function ($message) use (&$passed) {
            $passed = false;
        });

        $this->assertTrue($passed);
    }

    public function test_validation_fails_when_password_does_not_match(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        Auth::login($user);

        $rule = new MatchCurrentPassword();
        $failMessage = null;

        $rule->validate('password', 'wrong-password', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('The provided current password does not match your actual password.', $failMessage);
    }
}
