<?php

namespace Tests\Unit\Models\Settings;

use AdAstra\Models\SettingDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingDomainTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Fillable & table
    // -------------------------------------------------------------------------

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(
            ['name', 'handle', 'description', 'icon', 'sort_order'],
            (new SettingDomain)->getFillable()
        );
    }

    public function test_uses_correct_table(): void
    {
        $this->assertEquals('setting_domains', (new SettingDomain)->getTable());
    }

    // -------------------------------------------------------------------------
    // Casts
    // -------------------------------------------------------------------------

    public function test_sort_order_is_cast_to_integer(): void
    {
        $domain = SettingDomain::create([
            'name' => 'Test',
            'handle' => 'test',
            'sort_order' => '5',
        ]);

        $this->assertIsInt($domain->sort_order);
        $this->assertSame(5, $domain->sort_order);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function test_ordered_scope_sorts_by_sort_order_then_name(): void
    {
        SettingDomain::create(['name' => 'Zebra', 'handle' => 'zebra', 'sort_order' => 0]);
        SettingDomain::create(['name' => 'Alpha', 'handle' => 'alpha', 'sort_order' => 1]);
        SettingDomain::create(['name' => 'Beta', 'handle' => 'beta', 'sort_order' => 0]);

        $names = SettingDomain::ordered()->pluck('name')->toArray();

        $this->assertSame(['Beta', 'Zebra', 'Alpha'], $names);
    }

    // -------------------------------------------------------------------------
    // configFields()
    // -------------------------------------------------------------------------

    public function test_config_fields_returns_empty_array_for_unknown_domain(): void
    {
        $domain = new SettingDomain;
        $domain->handle = 'nonexistent_handle_xyz';

        $this->assertSame([], $domain->configFields());
    }

    public function test_config_fields_returns_fields_from_config(): void
    {
        config(['settings.sdt1' => [
            'name' => 'SDT1',
            'fields' => [
                ['handle' => 'foo', 'label' => 'Foo', 'type' => 'text', 'default' => 'bar', 'user_overridable' => false, 'hidden' => false],
                ['handle' => 'baz', 'label' => 'Baz', 'type' => 'integer', 'default' => 1, 'user_overridable' => true, 'hidden' => false],
            ],
        ]]);

        $domain = new SettingDomain;
        $domain->handle = 'sdt1';

        $fields = $domain->configFields();

        $this->assertCount(2, $fields);
        $this->assertSame('foo', $fields[0]['handle']);
        $this->assertSame('baz', $fields[1]['handle']);
    }

    // -------------------------------------------------------------------------
    // overridableConfigFields()
    // -------------------------------------------------------------------------

    public function test_overridable_config_fields_returns_only_overridable_visible_fields(): void
    {
        config(['settings.sdt2' => [
            'name' => 'SDT2',
            'fields' => [
                ['handle' => 'alpha', 'label' => 'Alpha', 'type' => 'text', 'default' => '', 'user_overridable' => true, 'hidden' => false],
                ['handle' => 'beta', 'label' => 'Beta', 'type' => 'text', 'default' => '', 'user_overridable' => false, 'hidden' => false],
                ['handle' => 'gamma', 'label' => 'Gamma', 'type' => 'integer', 'default' => 1, 'user_overridable' => true, 'hidden' => true],
            ],
        ]]);

        $domain = new SettingDomain;
        $domain->handle = 'sdt2';

        $overridable = $domain->overridableConfigFields();

        // Only alpha qualifies: overridable AND not hidden
        $this->assertCount(1, $overridable);
        $this->assertSame('alpha', $overridable[0]['handle']);
    }

    public function test_overridable_config_fields_returns_empty_when_none_qualify(): void
    {
        config(['settings.sdt3' => [
            'name' => 'SDT3',
            'fields' => [
                ['handle' => 'x', 'label' => 'X', 'type' => 'text', 'default' => '', 'user_overridable' => false, 'hidden' => false],
            ],
        ]]);

        $domain = new SettingDomain;
        $domain->handle = 'sdt3';

        $this->assertSame([], $domain->overridableConfigFields());
    }
}
