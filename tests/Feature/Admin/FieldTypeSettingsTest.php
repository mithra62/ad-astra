<?php

namespace Tests\Feature\Admin;

use App\Field\Types\EmailAddress;
use App\Field\Types\Text;
use App\Models\Field as FieldModel;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\Media\Library;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldTypeSettingsTest extends TestCase
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

    private function typeFor(string $class): FieldType
    {
        return FieldType::factory()->create(['object' => $class, 'settings' => []]);
    }

    // -------------------------------------------------------------------------
    // Basic HTTP
    // -------------------------------------------------------------------------

    public function test_returns_404_when_type_id_not_found(): void
    {
        $user = $this->makeSuperAdmin();

        $this->actingAs($user)
            ->get(route('fields.type_settings', ['type_id' => 99999]))
            ->assertNotFound();
    }

    public function test_returns_422_when_type_id_missing(): void
    {
        $user = $this->makeSuperAdmin();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->get(route('fields.type_settings'))
            ->assertUnprocessable();
    }

    public function test_returns_200_for_valid_type_id(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->typeFor(Text::class);

        $this->actingAs($user)
            ->get(route('fields.type_settings', ['type_id' => $type->id]))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Empty-state message
    // -------------------------------------------------------------------------

    public function test_returns_no_configurable_settings_message_for_email_address(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->typeFor(EmailAddress::class);

        $this->actingAs($user)
            ->get(route('fields.type_settings', ['type_id' => $type->id]))
            ->assertOk()
            ->assertSee('no configurable settings', false);
    }

    // -------------------------------------------------------------------------
    // Settings panel content
    // -------------------------------------------------------------------------

    public function test_returns_placeholder_input_for_text_type(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->typeFor(Text::class);

        $this->actingAs($user)
            ->get(route('fields.type_settings', ['type_id' => $type->id]))
            ->assertOk()
            ->assertSee('settings[placeholder]', false);
    }

    public function test_returns_max_length_input_for_text_type(): void
    {
        $user = $this->makeSuperAdmin();
        $type = $this->typeFor(Text::class);

        $this->actingAs($user)
            ->get(route('fields.type_settings', ['type_id' => $type->id]))
            ->assertOk()
            ->assertSee('settings[max_length]', false);
    }

    // -------------------------------------------------------------------------
    // DB-sourced options
    // -------------------------------------------------------------------------

    public function test_file_upload_panel_includes_library_options(): void
    {
        $user    = $this->makeSuperAdmin();
        $type    = $this->typeFor(\App\Field\Types\FileUpload::class);
        $library = Library::factory()->create(['name' => 'My Test Library']);

        $this->actingAs($user)
            ->get(route('fields.type_settings', ['type_id' => $type->id]))
            ->assertOk()
            ->assertSee('My Test Library', false);
    }

    // -------------------------------------------------------------------------
    // current_values pre-population via field_id
    // -------------------------------------------------------------------------

    public function test_pre_populates_current_values_when_field_id_provided(): void
    {
        $user  = $this->makeSuperAdmin();
        $type  = $this->typeFor(Text::class);
        $group = FieldGroup::factory()->create();

        $field = FieldModel::factory()->create([
            'field_type_id' => $type->id,
            'settings'      => ['placeholder' => 'My saved placeholder'],
        ]);
        $field->groups()->attach($group);

        $this->actingAs($user)
            ->get(route('fields.type_settings', [
                'type_id'  => $type->id,
                'field_id' => $field->id,
            ]))
            ->assertOk()
            ->assertSee('My saved placeholder', false);
    }
}
