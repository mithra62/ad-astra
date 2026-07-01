<?php

namespace Tests\Unit\Models\Settings;

use AdAstra\Models\SettingValue;
use AdAstra\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingValueTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Fillable & table
    // -------------------------------------------------------------------------

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(
            ['domain', 'field_handle', 'user_id', 'value_text', 'value_integer', 'value_float', 'value_boolean', 'value_json'],
            (new SettingValue)->getFillable()
        );
    }

    public function test_uses_correct_table(): void
    {
        $this->assertEquals('setting_values', (new SettingValue)->getTable());
    }

    // -------------------------------------------------------------------------
    // Typed column casts
    // -------------------------------------------------------------------------

    public function test_value_integer_is_cast_to_int(): void
    {
        $sv = SettingValue::create([
            'domain' => 'general', 'field_handle' => 'items_per_page',
            'user_id' => null, 'value_integer' => '25',
        ]);

        $this->assertIsInt($sv->value_integer);
        $this->assertSame(25, $sv->value_integer);
    }

    public function test_value_float_is_cast_to_float(): void
    {
        $sv = SettingValue::create([
            'domain' => 'media', 'field_handle' => 'ratio',
            'user_id' => null, 'value_float' => '1.5',
        ]);

        $this->assertIsFloat($sv->value_float);
        $this->assertSame(1.5, $sv->value_float);
    }

    public function test_value_boolean_is_cast_to_bool(): void
    {
        $sv = SettingValue::create([
            'domain' => 'general', 'field_handle' => 'maintenance',
            'user_id' => null, 'value_boolean' => 1,
        ]);

        $this->assertIsBool($sv->value_boolean);
        $this->assertTrue($sv->value_boolean);
    }

    public function test_value_json_is_cast_to_array(): void
    {
        $sv = SettingValue::create([
            'domain' => 'media', 'field_handle' => 'extensions',
            'user_id' => null, 'value_json' => ['jpg', 'png'],
        ]);

        $this->assertIsArray($sv->value_json);
        $this->assertSame(['jpg', 'png'], $sv->value_json);
    }

    public function test_user_id_is_cast_to_integer(): void
    {
        $user = User::factory()->create();

        $sv = SettingValue::create([
            'domain' => 'general', 'field_handle' => 'timezone',
            'user_id' => $user->id, 'value_text' => 'UTC',
        ]);

        $this->assertIsInt($sv->user_id);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_user_returns_belongs_to(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new SettingValue)->user());
    }

    public function test_user_id_can_be_null_for_system_values(): void
    {
        $sv = SettingValue::create([
            'domain' => 'general', 'field_handle' => 'site_name',
            'user_id' => null, 'value_text' => 'My Site',
        ]);

        $this->assertNull($sv->user_id);
        $this->assertNull($sv->user);
    }

    public function test_user_relationship_resolves_correctly(): void
    {
        $user = User::factory()->create();

        $sv = SettingValue::create([
            'domain' => 'general', 'field_handle' => 'timezone',
            'user_id' => $user->id, 'value_text' => 'America/New_York',
        ]);

        $this->assertInstanceOf(User::class, $sv->user);
        $this->assertSame($user->id, $sv->user->id);
    }

    // -------------------------------------------------------------------------
    // Unique constraint
    // -------------------------------------------------------------------------

    /**
     * The unique index on (domain, field_handle, user_id) enforces that a given
     * user cannot have two override rows for the same field. It does NOT prevent
     * duplicate system rows (user_id = NULL) because SQL treats NULL != NULL in
     * unique indexes — application-layer updateOrCreate handles system deduplication.
     */
    public function test_unique_constraint_prevents_duplicate_user_overrides(): void
    {
        $user = User::factory()->create();

        SettingValue::create([
            'domain' => 'general', 'field_handle' => 'timezone',
            'user_id' => $user->id, 'value_text' => 'UTC',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SettingValue::create([
            'domain' => 'general', 'field_handle' => 'timezone',
            'user_id' => $user->id, 'value_text' => 'America/New_York',
        ]);
    }

    public function test_same_handle_can_exist_for_different_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        SettingValue::create(['domain' => 'general', 'field_handle' => 'timezone', 'user_id' => null, 'value_text' => 'UTC']);
        SettingValue::create(['domain' => 'general', 'field_handle' => 'timezone', 'user_id' => $userA->id, 'value_text' => 'America/New_York']);
        SettingValue::create(['domain' => 'general', 'field_handle' => 'timezone', 'user_id' => $userB->id, 'value_text' => 'Europe/London']);

        $this->assertDatabaseCount('setting_values', 3);
    }

    // -------------------------------------------------------------------------
    // Only one typed column populated per row
    // -------------------------------------------------------------------------

    public function test_unused_typed_columns_remain_null(): void
    {
        $sv = SettingValue::create([
            'domain' => 'general', 'field_handle' => 'timezone',
            'user_id' => null, 'value_text' => 'UTC',
        ]);

        $this->assertNull($sv->value_integer);
        $this->assertNull($sv->value_float);
        $this->assertNull($sv->value_boolean);
        $this->assertNull($sv->value_json);
    }
}
