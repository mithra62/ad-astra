<?php

namespace Tests\Feature\Field;

use AdAstra\Field\Types\Money;
use AdAstra\Field\Types\Time;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use AdAstra\Models\Role;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves validation rules attached to the new field types reject bad input at
 * the HTTP boundary, returning friendly form errors via Laravel's normal flow.
 * This is the user-facing guarantee the design rests on.
 */
class RequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_rejects_too_many_decimal_places_with_friendly_error(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndTypeWithMoneyField();

        $response = $this->actingAs($user)
            ->from(route('entries.create', ['group_id' => $group->id]))
            ->post(route('entries.store', ['group_id' => $group->id]), [
                'type_handle' => $type->handle,
                'title' => 'Bad Money',
                'handle' => 'bad-money',
                'status' => 'draft',
                'fields' => ['price' => '42.555'],
            ]);

        $response->assertSessionHasErrors(['fields.price']);
        $errors = session('errors')->get('fields.price');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('USD', $errors[0]);
        $this->assertStringContainsString('decimal places', $errors[0]);
    }

    public function test_money_accepts_valid_input_and_persists_minor_units(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndTypeWithMoneyField();

        $response = $this->actingAs($user)->post(route('entries.store', ['group_id' => $group->id]), [
            'type_handle' => $type->handle,
            'title' => 'Good Money',
            'handle' => 'good-money',
            'status' => 'draft',
            'fields' => ['price' => '42.50'],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $entry = Entry::where('handle', 'good-money')->firstOrFail();

        $this->assertDatabaseHas('field_values', [
            'fieldable_id' => $entry->id,
            'value_integer' => 4250,
        ]);
    }

    public function test_time_rejects_invalid_format_with_friendly_error(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndTypeWithTimeField();

        $response = $this->actingAs($user)
            ->from(route('entries.create', ['group_id' => $group->id]))
            ->post(route('entries.store', ['group_id' => $group->id]), [
                'type_handle' => $type->handle,
                'title' => 'Bad Time',
                'handle' => 'bad-time',
                'status' => 'draft',
                'fields' => ['open_time' => '25:00'],
            ]);

        $response->assertSessionHasErrors(['fields.open_time']);
        $errors = session('errors')->get('fields.open_time');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('valid time', $errors[0]);
    }

    public function test_time_accepts_valid_input_and_canonicalizes(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndTypeWithTimeField();

        $response = $this->actingAs($user)->post(route('entries.store', ['group_id' => $group->id]), [
            'type_handle' => $type->handle,
            'title' => 'Good Time',
            'handle' => 'good-time',
            'status' => 'draft',
            'fields' => ['open_time' => '9:30'],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $entry = Entry::where('handle', 'good-time')->firstOrFail();

        // Stored as canonical 24-hour HH:MM (include_seconds defaults to false).
        $this->assertDatabaseHas('field_values', [
            'fieldable_id' => $entry->id,
            'value_text' => '09:30',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'super admin',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array{0: EntryGroup, 1: EntryType}
     */
    private function makeEntryGroupAndTypeWithMoneyField(): array
    {
        return $this->makeEntryGroupAndTypeWithField(
            handle: 'price',
            typeClass: Money::class,
            typeName: 'Money',
            settings: ['currency' => 'USD'],
        );
    }

    /**
     * @return array{0: EntryGroup, 1: EntryType}
     */
    private function makeEntryGroupAndTypeWithTimeField(): array
    {
        return $this->makeEntryGroupAndTypeWithField(
            handle: 'open_time',
            typeClass: Time::class,
            typeName: 'Time',
            settings: [],
        );
    }

    /**
     * @return array{0: EntryGroup, 1: EntryType}
     */
    private function makeEntryGroupAndTypeWithField(
        string $handle,
        string $typeClass,
        string $typeName,
        array  $settings,
    ): array
    {
        $statusGroup = StatusGroup::factory()->create();
        Status::factory()->default()->create([
            'status_group_id' => $statusGroup->id,
            'handle' => 'draft',
            'name' => 'Draft',
        ]);

        $fieldType = FieldType::firstOrCreate(
            ['object' => $typeClass],
            ['name' => $typeName],
        );
        $field = Field::factory()->create([
            'field_type_id' => $fieldType->id,
            'handle' => $handle,
            'settings' => $settings,
        ]);

        $layout = FieldLayout::factory()->create();
        $tab = Tab::factory()->create(['field_layout_id' => $layout->id]);
        TabElement::factory()->create([
            'field_layout_tab_id' => $tab->id,
            'field_id' => $field->id,
        ]);

        $group = EntryGroup::factory()->create([
            'status_group_id' => $statusGroup->id,
            'field_layout_id' => $layout->id,
        ]);

        $type = EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'field_layout_id' => null,
        ]);

        return [$group, $type];
    }
}
