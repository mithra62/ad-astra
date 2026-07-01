<?php

namespace Tests\Unit\Field\Types;

use AdAstra\Field\Types\Users;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $settings = []): Users
    {
        return new Users($settings, null);
    }

    // -------------------------------------------------------------------------
    // storageColumn / basics
    // -------------------------------------------------------------------------

    public function test_storage_column_is_value_json(): void
    {
        $this->assertSame('value_json', $this->make()->storageColumn());
    }

    public function test_settings_form_has_expected_keys(): void
    {
        $form = $this->make()->settingsForm();
        $this->assertArrayHasKey('roles', $form);
        $this->assertArrayHasKey('limit', $form);
        $this->assertArrayHasKey('display', $form);
    }

    // -------------------------------------------------------------------------
    // cast()
    // -------------------------------------------------------------------------

    public function test_cast_returns_array_of_integers(): void
    {
        $result = $this->make()->cast([1, 2, 3]);
        $this->assertSame([1, 2, 3], $result);
        foreach ($result as $id) {
            $this->assertIsInt($id);
        }
    }

    public function test_cast_decodes_json_string(): void
    {
        $result = $this->make()->cast('[1,2]');
        $this->assertSame([1, 2], $result);
    }

    public function test_cast_returns_empty_array_for_null(): void
    {
        $this->assertSame([], $this->make()->cast(null));
    }

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    public function test_validate_returns_true_for_null(): void
    {
        $this->assertTrue($this->make()->validate(null));
    }

    public function test_validate_returns_true_for_empty_array(): void
    {
        $this->assertTrue($this->make()->validate([]));
    }

    public function test_validate_enforces_limit(): void
    {
        $users = User::factory()->count(3)->create();
        $ids = $users->pluck('id')->all();

        $type = $this->make(['limit' => 2]);
        $result = $type->validate($ids);
        $this->assertIsString($result);
        $this->assertStringContainsString('2', $result);
    }

    public function test_validate_returns_error_for_nonexistent_user(): void
    {
        $result = $this->make()->validate([99999]);
        $this->assertIsString($result);
        $this->assertStringContainsString('no longer exist', $result);
    }

    public function test_validate_returns_true_for_valid_existing_users(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($this->make()->validate([$user->id]));
    }

    // -------------------------------------------------------------------------
    // value() — sensitive column exclusion
    // -------------------------------------------------------------------------

    public function test_value_returns_collection_of_users(): void
    {
        $user = User::factory()->create();
        $result = $this->make()->value([$user->id]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }

    public function test_value_does_not_expose_password_column(): void
    {
        $user = User::factory()->create();
        $result = $this->make()->value([$user->id]);

        $attributes = $result->first()->getAttributes();
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertArrayNotHasKey('remember_token', $attributes);
    }

    public function test_value_exposes_safe_columns_only(): void
    {
        $user = User::factory()->create();
        $result = $this->make()->value([$user->id]);

        $attributes = $result->first()->getAttributes();
        $this->assertArrayHasKey('id', $attributes);
        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('email', $attributes);
    }

    public function test_value_returns_empty_collection_for_empty_input(): void
    {
        $result = $this->make()->value([]);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_value_preserves_order_of_ids(): void
    {
        $users = User::factory()->count(3)->create();
        $ids = $users->pluck('id')->reverse()->values()->all();

        $result = $this->make()->value($ids);
        $this->assertEquals($ids, $result->pluck('id')->all());
    }
}
