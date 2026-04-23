<?php

namespace Tests\Unit\Models;

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
            (new Field)->getFillable()
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
        $this->assertContains('fieldType', $prop->getValue(new Field));
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
}
