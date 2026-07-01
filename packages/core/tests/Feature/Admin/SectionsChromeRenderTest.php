<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke tests that the high-traffic admin index/landing screens render (HTTP 200)
 * after the UI-kit chrome conversion (breadcrumbs, page-header, section_nav).
 */
class SectionsChromeRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @return array<string, array{0: string}> */
    public static function routes(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'account.details' => ['account.details'],
            'account.password' => ['account.password'],
            'roles.index' => ['roles.index'],
            'statuses.groups' => ['statuses.groups'],
            'fields.groups' => ['fields.groups'],
            'entries.groups' => ['entries.groups'],
            'entries.types' => ['entries.types'],
            'field-layouts' => ['field-layouts'],
            'media.libraries' => ['media.libraries'],
            'account.settings' => ['account.settings'],
            'account.tokens.index' => ['account.tokens.index'],
            'roles.create' => ['roles.create'],
            'statuses.groups.create' => ['statuses.groups.create'],
            'fields.groups.create' => ['fields.groups.create'],
            'entries.groups.create' => ['entries.groups.create'],
            'entries.types.create' => ['entries.types.create'],
            'field-layouts.create' => ['field-layouts.create'],
            'media.libraries.create' => ['media.libraries.create'],
        ];
    }

    #[DataProvider('routes')]
    public function test_screen_renders(string $routeName): void
    {
        $this->actingAs($this->admin)
            ->get(route($routeName))
            ->assertOk();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }
}
