<?php

namespace Tests\Feature\Admin;

use App\Field\Types\Select;
use App\Field\Types\Slider;
use App\Field\Types\Users;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldSettingsValidationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function makeGroup(): FieldGroup
    {
        return FieldGroup::factory()->create();
    }

    private function selectType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => Select::class],
            ['name' => 'Select', 'handle' => 'select', 'settings' => []]
        );
    }

    private function sliderType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => Slider::class],
            ['name' => 'Slider', 'handle' => 'slider', 'settings' => []]
        );
    }

    private function usersType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => Users::class],
            ['name' => 'Users', 'handle' => 'users', 'settings' => []]
        );
    }

    private function basePayload(FieldType $type, FieldGroup $group, array $overrides = []): array
    {
        return array_merge([
            'group_id'      => $group->id,
            'field_type_id' => $type->id,
            'name'          => 'Test Field',
            'handle'        => 'test_field',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Select — options required
    // -------------------------------------------------------------------------

    public function test_select_fails_when_options_are_empty(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->selectType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['options' => []],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.options']);
    }

    public function test_select_fails_when_options_are_absent(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->selectType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => [],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.options']);
    }

    // -------------------------------------------------------------------------
    // Slider — min/max required
    // -------------------------------------------------------------------------

    public function test_slider_fails_when_min_is_missing(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->sliderType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['max' => 100],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.min']);
    }

    public function test_slider_fails_when_max_is_missing(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->sliderType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['min' => 0],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.max']);
    }

    public function test_slider_passes_with_valid_min_and_max(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->sliderType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['min' => 0, 'max' => 100],
            ]))
            ->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // Users — limit must be >= 0
    // -------------------------------------------------------------------------

    public function test_users_fails_when_limit_is_negative(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->usersType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['limit' => -1],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.limit']);
    }

    public function test_users_passes_when_limit_is_zero(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->usersType();
        $group = $this->makeGroup();

        $this->actingAs($user)
            ->post(route('fields.store', ['group_id' => $group->id]), $this->basePayload($type, $group, [
                'settings' => ['limit' => 0],
            ]))
            ->assertRedirect();
    }
}
