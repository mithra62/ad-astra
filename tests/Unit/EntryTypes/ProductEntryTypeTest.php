<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\ProductEntryType;
use App\Models\Entry;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): ProductEntryType
    {
        $record = EntryType::factory()->create(['class' => ProductEntryType::class]);
        return new ProductEntryType($record);
    }

    // -------------------------------------------------------------------------
    // beforeCreate — normalisation (no longer throws)
    // -------------------------------------------------------------------------

    public function test_before_create_passes_when_price_is_zero(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate(['fields' => ['price' => 0]]);

        $this->assertSame(0, $result['fields']['price']);
    }

    public function test_before_create_passes_when_price_is_positive(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate(['fields' => ['price' => 29.99]]);

        $this->assertSame(29.99, $result['fields']['price']);
    }

    public function test_before_create_passes_when_sale_price_is_less_than_price(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate(['fields' => ['price' => 100, 'sale_price' => 79]]);

        $this->assertSame(79, $result['fields']['sale_price']);
    }

    public function test_before_create_passes_when_only_price_is_set(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate(['fields' => ['price' => 49.99]]);

        $this->assertSame(49.99, $result['fields']['price']);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate — stock auto-status
    // -------------------------------------------------------------------------

    public function test_before_update_sets_out_of_stock_when_stock_quantity_reaches_zero(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, ['fields' => ['stock_quantity' => 0]]);

        $this->assertSame('out-of-stock', $result['status']);
    }

    public function test_before_update_does_not_change_status_when_stock_is_positive(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, ['fields' => ['stock_quantity' => 5]]);

        $this->assertArrayNotHasKey('status', $result);
    }

    public function test_before_update_does_not_change_status_when_stock_absent(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        $result = $type->beforeUpdate($entry, ['title' => 'Updated Product']);

        $this->assertArrayNotHasKey('status', $result);
    }

    // -------------------------------------------------------------------------
    // validate() — pricing guards
    // -------------------------------------------------------------------------

    public function test_validate_returns_error_when_price_is_negative(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['fields' => ['price' => -1]]);

        $this->assertArrayHasKey('price', $errors);
        $this->assertStringContainsString('negative', $errors['price']);
    }

    public function test_validate_returns_error_when_sale_price_equals_price(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['fields' => ['price' => 100, 'sale_price' => 100]]);

        $this->assertArrayHasKey('sale_price', $errors);
        $this->assertStringContainsString('less than price', $errors['sale_price']);
    }

    public function test_validate_returns_error_when_sale_price_exceeds_price(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['fields' => ['price' => 50, 'sale_price' => 75]]);

        $this->assertArrayHasKey('sale_price', $errors);
    }

    public function test_validate_returns_error_when_sale_price_set_and_price_is_zero(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['fields' => ['price' => 0, 'sale_price' => 10]]);

        $this->assertArrayHasKey('sale_price', $errors);
        $this->assertStringContainsString('price is zero', $errors['sale_price']);
    }

    public function test_validate_passes_when_sale_price_is_less_than_price(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['fields' => ['price' => 100, 'sale_price' => 79]]);

        $this->assertArrayNotHasKey('price', $errors);
        $this->assertArrayNotHasKey('sale_price', $errors);
    }

    public function test_validate_passes_when_price_is_zero_without_sale_price(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['fields' => ['price' => 0]]);

        $this->assertEmpty($errors);
    }

    public function test_validate_passes_when_no_pricing_fields_present(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['fields' => []]);

        $this->assertEmpty($errors);
    }

    // -------------------------------------------------------------------------
    // validate() — SKU / publish gate
    // -------------------------------------------------------------------------

    public function test_validate_returns_error_when_sku_empty_on_publish(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'status' => 'published',
            'fields' => ['sku' => ''],
        ]);

        $this->assertArrayHasKey('sku', $errors);
    }

    public function test_validate_passes_when_sku_provided_on_publish(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'status' => 'published',
            'fields' => ['sku' => 'WIDGET-001'],
        ]);

        $this->assertArrayNotHasKey('sku', $errors);
    }

    public function test_validate_passes_when_status_is_draft_without_sku(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['status' => 'draft', 'fields' => []]);

        $this->assertEmpty($errors);
    }

    public function test_validate_can_return_both_pricing_and_sku_errors_simultaneously(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'status' => 'published',
            'fields' => ['price' => -5, 'sku' => ''],
        ]);

        $this->assertArrayHasKey('price', $errors);
        $this->assertArrayHasKey('sku', $errors);
    }
}
