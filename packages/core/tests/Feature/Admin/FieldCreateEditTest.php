<?php

namespace Tests\Feature\Admin;

use AdAstra\Field\Types\Text;
use AdAstra\Field\Types\EmailAddress;
use AdAstra\Models\Field as FieldModel;
use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\Role;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldCreateEditTest extends TestCase
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

    private function textType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => Text::class],
            ['name' => 'Text', 'handle' => 'text', 'settings' => []]
        );
    }

    private function emailType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => EmailAddress::class],
            ['name' => 'Email Address', 'handle' => 'email_address', 'settings' => []]
        );
    }

    // -------------------------------------------------------------------------
    // Create page
    // -------------------------------------------------------------------------

    public function test_create_page_renders_settings_card(): void
    {
        $user = $this->makeSuperAdmin();
        $group = $this->makeGroup();
        $this->textType();

        $this->actingAs($user)
            ->get(route('fields.create', $group->id))
            ->assertOk()
            ->assertSee('Field Type Settings', false);
    }

    public function test_create_page_renders_initial_settings_for_text_type(): void
    {
        $user = $this->makeSuperAdmin();
        $group = $this->makeGroup();
        $this->textType();

        $this->actingAs($user)
            ->get(route('fields.create', $group->id))
            ->assertOk()
            ->assertSee('settings[placeholder]', false);
    }

    public function test_create_page_repopulates_settings_after_validation_failure(): void
    {
        $user = $this->makeSuperAdmin();
        $group = $this->makeGroup();
        $type = $this->textType();

        $this->actingAs($user)
            ->withSession(['_old_input' => [
                'field_type_id' => (string)$type->id,
                'settings' => ['placeholder' => 'Repopulated value'],
            ]])
            ->get(route('fields.create', $group->id))
            ->assertOk()
            ->assertSee('Repopulated value', false);
    }

    // -------------------------------------------------------------------------
    // Edit page
    // -------------------------------------------------------------------------

    public function test_edit_page_renders_settings_card(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->textType();
        $group = $this->makeGroup();
        $field = FieldModel::factory()->create([
            'field_type_id' => $type->id,
            'settings' => [],
        ]);
        $field->groups()->attach($group);

        $this->actingAs($user)
            ->get(route('fields.edit', $field->id))
            ->assertOk()
            ->assertSee('Field Type Settings', false);
    }

    public function test_edit_page_pre_populates_saved_settings(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->textType();
        $group = $this->makeGroup();
        $field = FieldModel::factory()->create([
            'field_type_id' => $type->id,
            'settings' => ['placeholder' => 'Saved placeholder'],
        ]);
        $field->groups()->attach($group);

        $this->actingAs($user)
            ->get(route('fields.edit', $field->id))
            ->assertOk()
            ->assertSee('Saved placeholder', false);
    }

    public function test_edit_page_renders_no_settings_message_for_email_type(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->emailType();
        $group = $this->makeGroup();
        $field = FieldModel::factory()->create([
            'field_type_id' => $type->id,
            'settings' => [],
        ]);
        $field->groups()->attach($group);

        $this->actingAs($user)
            ->get(route('fields.edit', $field->id))
            ->assertOk()
            ->assertSee('no configurable settings', false);
    }
}
