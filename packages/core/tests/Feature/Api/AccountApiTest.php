<?php

namespace Tests\Feature\Api;

use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature coverage for the Account API controller. Only GET /api/v1/account
 * (Account::show) is wired into the API routes; it is a thin authenticated
 * endpoint with no permission gate beyond auth:sanctum.
 */
class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_rejects_guests_with_401(): void
    {
        $this->getJson('/api/v1/account')->assertUnauthorized();
    }

    public function test_show_returns_ok_for_authenticated_user(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->getJson('/api/v1/account')->assertOk();
    }
}
