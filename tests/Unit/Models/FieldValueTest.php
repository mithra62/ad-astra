<?php

namespace Tests\Unit\Models;

use App\Field\Types\Text;
use App\Models\Category;
use App\Models\Field;
use App\Models\Field\Type;
use App\Models\FieldValue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(
            [
                'field_id',
                'fieldable_id',
                'fieldable_type',
                'value_text',
                'value_integer',
                'value_float',
                'value_date',
                'value_boolean',
                'value_json',
            ],
            (new FieldValue)->getFillable()
        );
    }

    public function test_always_eager_loads_field(): void
    {
        $prop = (new \ReflectionClass(FieldValue::class))->getProperty('with');
        $prop->setAccessible(true);
        $this->assertContains('field', $prop->getValue(new FieldValue));
    }

    public function test_casts_value_integer_to_integer(): void
    {
        $fv = FieldValue::factory()->create(['value_integer' => '42']);

        $this->assertIsInt($fv->value_integer);
        $this->assertEquals(42, $fv->value_integer);
    }

    public function test_casts_value_float_to_float(): void
    {
        $fv = FieldValue::factory()->create(['value_float' => '3.14']);

        $this->assertIsFloat($fv->value_float);
        $this->assertEqualsWithDelta(3.14, $fv->value_float, 0.001);
    }

    public function test_casts_value_date_to_datetime(): void
    {
        $fv = FieldValue::factory()->create(['value_date' => '2026-01-15 10:00:00']);

        $this->assertInstanceOf(Carbon::class, $fv->value_date);
    }

    public function test_casts_value_boolean_to_boolean(): void
    {
        $fv = FieldValue::factory()->create(['value_boolean' => 1]);

        $this->assertIsBool($fv->value_boolean);
        $this->assertTrue($fv->value_boolean);
    }

    public function test_casts_value_json_to_array(): void
    {
        $fv = FieldValue::factory()->create(['value_json' => ['a' => 1]]);

        $this->assertIsArray($fv->value_json);
        $this->assertEquals(['a' => 1], $fv->value_json);
    }

    public function test_field_relationship_is_belongs_to(): void
    {
        $field = Field::factory()->create();
        $fv = FieldValue::factory()->create(['field_id' => $field->id]);

        $this->assertInstanceOf(BelongsTo::class, $fv->field());
        $this->assertEquals($field->id, $fv->field->id);
    }

    public function test_fieldable_relationship_is_morph_to(): void
    {
        $fv = FieldValue::factory()->create();

        $this->assertInstanceOf(MorphTo::class, $fv->fieldable());
    }

    public function test_resolved_value_returns_value_text_when_field_type_is_null(): void
    {
        $fv = new FieldValue(['value_text' => 'hello']);
        $fv->setRelation('field', null);

        $this->assertEquals('hello', $fv->resolvedValue());
    }

    public function test_resolved_value_uses_storage_column_from_field_type(): void
    {
        $type = Type::factory()->create([
            'object' => Text::class,
        ]);
        $field = Field::factory()->create(['field_type_id' => $type->id]);
        $fv = FieldValue::factory()->create([
            'field_id' => $field->id,
            'value_text' => 'stored text',
        ]);

        $this->assertEquals('stored text', $fv->resolvedValue());
    }

    public function test_field_value_can_be_associated_with_category_via_morphic_relation(): void
    {
        $category = Category::factory()->create();
        $field = Field::factory()->create();

        $fv = FieldValue::create([
            'field_id' => $field->id,
            'fieldable_id' => $category->id,
            'fieldable_type' => Category::class,
            'value_text' => 'test value',
        ]);

        $fv->refresh();
        $this->assertEquals($category->id, $fv->fieldable_id);
        $this->assertEquals(Category::class, $fv->fieldable_type);
    }
}
