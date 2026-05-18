<?php

namespace Tests\Unit\Models;

use App\Field\AbstractField;
use App\Field\Types\Text;
use App\Models\Field;
use App\Models\Field\Group;
use App\Models\Field\Type;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(
            ['field_type_id', 'name', 'handle', 'label', 'instructions', 'settings', 'hidden'],
            (new Field())->getFillable()
        );
    }

    public function test_casts_settings_to_array(): void
    {
        $field = Field::factory()->create(['settings' => ['key' => 'value']]);

        $this->assertIsArray($field->settings);
        $this->assertEquals(['key' => 'value'], $field->settings);
    }

    public function test_casts_hidden_to_boolean(): void
    {
        $field = Field::factory()->create(['hidden' => 1]);

        $this->assertIsBool($field->hidden);
        $this->assertTrue($field->hidden);
    }

    public function test_always_eager_loads_field_type(): void
    {
        $prop = (new \ReflectionClass(Field::class))->getProperty('with');
        $prop->setAccessible(true);
        $this->assertContains('fieldType', $prop->getValue(new Field()));
    }

    public function test_field_type_relationship_is_belongs_to(): void
    {
        $type = Type::factory()->create();
        $field = Field::factory()->create(['field_type_id' => $type->id]);

        $this->assertInstanceOf(BelongsTo::class, $field->fieldType());
        $this->assertEquals($type->id, $field->fieldType->id);
    }

    public function test_field_values_relationship_is_has_many(): void
    {
        $field = Field::factory()->create();

        $this->assertInstanceOf(HasMany::class, $field->fieldValues());
    }

    public function test_groups_relationship_is_morph_to_many(): void
    {
        $field = Field::factory()->create();

        $this->assertInstanceOf(MorphToMany::class, $field->groups());
    }

    public function test_can_attach_to_field_groups(): void
    {
        $field = Field::factory()->create();
        $group = Group::factory()->create();
        $field->groups()->attach($group);

        $this->assertCount(1, $field->groups);
        $this->assertEquals($group->id, $field->groups->first()->id);
    }

    // -------------------------------------------------------------------------
    // typeInstance()
    // -------------------------------------------------------------------------

    public function test_type_instance_returns_abstract_field(): void
    {
        $type  = Type::factory()->create(['object' => Text::class]);
        $field = Field::factory()->create(['field_type_id' => $type->id]);

        $this->assertInstanceOf(AbstractField::class, $field->typeInstance());
    }

    public function test_type_instance_merges_field_settings_into_instance(): void
    {
        $type  = Type::factory()->create(['object' => Text::class, 'settings' => []]);
        $field = Field::factory()->create([
            'field_type_id' => $type->id,
            'settings'      => ['placeholder' => 'Search…'],
        ]);

        $instance = $field->typeInstance();

        $this->assertSame('Search…', $instance->getSetting('placeholder'));
    }

    public function test_type_instance_field_settings_override_type_settings(): void
    {
        $type  = Type::factory()->create(['object' => Text::class, 'settings' => ['placeholder' => 'Type default']]);
        $field = Field::factory()->create([
            'field_type_id' => $type->id,
            'settings'      => ['placeholder' => 'Field override'],
        ]);

        $instance = $field->typeInstance();

        $this->assertSame('Field override', $instance->getSetting('placeholder'));
    }

    public function test_type_instance_handles_null_field_settings(): void
    {
        $type  = Type::factory()->create(['object' => Text::class]);
        $field = Field::factory()->create(['field_type_id' => $type->id, 'settings' => null]);

        $this->assertInstanceOf(AbstractField::class, $field->typeInstance());
    }

    public function test_type_instance_attaches_field_to_returned_instance(): void
    {
        $type  = Type::factory()->create(['object' => Text::class]);
        $field = Field::factory()->create(['field_type_id' => $type->id, 'handle' => 'attached']);

        $instance = $field->typeInstance();

        // Read the protected `field` property via reflection — the field type
        // base class doesn't expose a getter, but production code reads it
        // directly (e.g. Media::render(), FileUpload::render()).
        $ref = new \ReflectionProperty(AbstractField::class, 'field');
        $ref->setAccessible(true);

        $attached = $ref->getValue($instance);
        $this->assertNotNull($attached);
        $this->assertSame($field->id, $attached->id);
        $this->assertSame('attached', $attached->handle);
    }

    // -------------------------------------------------------------------------
    // render()
    // -------------------------------------------------------------------------

    public function test_render_delegates_to_type_instance(): void
    {
        $type  = Type::factory()->create(['object' => Text::class]);
        $field = Field::factory()->create(['field_type_id' => $type->id]);

        // Text::render() calls a view; we just assert it returns a string
        // without throwing, confirming the delegation path works end-to-end.
        $this->assertIsString($field->render());
    }
}
