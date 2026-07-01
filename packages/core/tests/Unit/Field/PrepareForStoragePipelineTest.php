<?php

namespace Tests\Unit\Field;

use AdAstra\Field\Types\Money;
use AdAstra\Models\Category;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Repositories\AbstractFieldableRepository;
use AdAstra\Traits\Field\PersistsFieldValues;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Proves the prepareForStorage / prepareForQuery hooks fire at every
 * persistence and query call site. If Money is normalized correctly here,
 * any future Fieldable model following the same patterns inherits the
 * behavior with no extra work.
 */
class PrepareForStoragePipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_field_values_trait_normalizes_money(): void
    {
        $field = $this->makeMoneyField('price', ['currency' => 'USD']);
        $category = Category::factory()->create();

        $subject = new class () {
            use PersistsFieldValues;
        };

        $subject->setField($category, 'price', '42.50');

        $this->assertDatabaseHas('field_values', [
            'field_id' => $field->id,
            'fieldable_id' => $category->id,
            'value_integer' => 4250,
        ]);
    }

    public function test_persists_field_values_set_fields_normalizes_money(): void
    {
        $field = $this->makeMoneyField('price', ['currency' => 'JPY']);
        $category = Category::factory()->create();

        $subject = new class () {
            use PersistsFieldValues;
        };

        $subject->setFields($category, ['price' => '500']);

        $this->assertDatabaseHas('field_values', [
            'field_id' => $field->id,
            'fieldable_id' => $category->id,
            'value_integer' => 500,
        ]);
    }

    public function test_abstract_fieldable_repository_normalizes_money(): void
    {
        $field = $this->makeMoneyField('price', ['currency' => 'USD']);
        $category = Category::factory()->create();

        // Anonymous repo extending the abstract base. Same code path that
        // CategoryRepository and MediaRepository use; if this passes, both
        // are covered (and every future repo that extends the abstract).
        $repo = new class () extends AbstractFieldableRepository {
            public ?Collection $layout = null;

            public function resolveLayoutFields(Model $model): Collection
            {
                return $this->layout ?? collect();
            }

            public function call(Model $model, array $fields): void
            {
                $this->applyFieldValues($model, $fields);
            }
        };

        $repo->layout = collect([$field]);
        $repo->call($category, ['price' => '99.99']);

        $this->assertDatabaseHas('field_values', [
            'field_id' => $field->id,
            'fieldable_id' => $category->id,
            'value_integer' => 9999,
        ]);
    }

    public function test_entry_query_builder_normalizes_money_in_where_field(): void
    {
        $field = $this->makeMoneyField('price', ['currency' => 'USD']);

        // Two raw FieldValue rows: one priced at $42.50 (4250 cents) and one at $99.99 (9999 cents).
        $entryA = $this->makeEntry();
        $entryB = $this->makeEntry();
        \AdAstra\Models\FieldValue::create([
            'field_id' => $field->id,
            'fieldable_id' => $entryA->id,
            'fieldable_type' => $entryA->getMorphClass(),
            'value_integer' => 4250,
        ]);
        \AdAstra\Models\FieldValue::create([
            'field_id' => $field->id,
            'fieldable_id' => $entryB->id,
            'fieldable_type' => $entryB->getMorphClass(),
            'value_integer' => 9999,
        ]);

        // Query with the wire-format "42.50" should match the entry stored as 4250.
        $matches = (new \AdAstra\Builders\EntryQueryBuilder())
            ->whereField('price', '42.50')
            ->get()
            ->pluck('id')
            ->all();

        $this->assertSame([$entryA->id], $matches);
    }

    public function test_entry_query_builder_does_not_throw_on_invalid_query_input(): void
    {
        $this->makeMoneyField('price', ['currency' => 'USD']);

        // Bad query input must not raise — just return zero results.
        $matches = (new \AdAstra\Builders\EntryQueryBuilder())
            ->whereField('price', 'garbage')
            ->get()
            ->pluck('id')
            ->all();

        $this->assertSame([], $matches);
    }

    public function test_no_op_default_does_not_alter_non_money_values(): void
    {
        // Regression guard: existing field types whose prepareForStorage is
        // the default no-op must round-trip values byte-identical.
        $textField = $this->makeTextField('bio');
        $category = Category::factory()->create();

        $subject = new class () {
            use PersistsFieldValues;
        };

        $subject->setField($category, 'bio', 'Hello world');

        $this->assertDatabaseHas('field_values', [
            'field_id' => $textField->id,
            'fieldable_id' => $category->id,
            'value_text' => 'Hello world',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeMoneyField(string $handle, array $settings = []): Field
    {
        $type = FieldType::firstOrCreate(
            ['object' => Money::class],
            ['name' => 'Money'],
        );

        return Field::factory()->create([
            'field_type_id' => $type->id,
            'handle' => $handle,
            'settings' => $settings,
        ]);
    }

    private function makeTextField(string $handle): Field
    {
        $type = FieldType::firstOrCreate(
            ['object' => \AdAstra\Field\Types\Text::class],
            ['name' => 'Text'],
        );

        return Field::factory()->create([
            'field_type_id' => $type->id,
            'handle' => $handle,
        ]);
    }

    private function makeEntry(): \AdAstra\Models\Entry
    {
        $statusGroup = \AdAstra\Models\StatusGroup::factory()->create();
        \AdAstra\Models\Status::factory()->default()->create([
            'status_group_id' => $statusGroup->id,
            'handle' => 'draft',
        ]);
        $group = \AdAstra\Models\EntryGroup::factory()->create(['status_group_id' => $statusGroup->id]);
        $type = \AdAstra\Models\EntryType::factory()->create(['entry_group_id' => $group->id]);

        return \AdAstra\Models\Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
        ]);
    }
}
