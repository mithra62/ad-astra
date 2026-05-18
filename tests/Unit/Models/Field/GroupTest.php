<?php

namespace Tests\Unit\Models\Field;

use App\Models\Field;
use App\Models\Field\Group;
use App\Models\Field\Type;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new Group;

        $this->assertEquals(['name', 'handle', 'description'], $model->getFillable());
    }

    public function test_uses_field_groups_table(): void
    {
        $this->assertEquals('field_groups', (new Group)->getTable());
    }

    public function test_fields_relationship_is_morph_to_many(): void
    {
        $group = Group::factory()->create();

        $this->assertInstanceOf(MorphToMany::class, $group->fields());
    }

    public function test_fields_returns_associated_fields(): void
    {
        $group = Group::factory()->create();
        $field = Field::factory()->create();
        $group->fields()->attach($field);

        $this->assertCount(1, $group->fields);
        $this->assertEquals($field->id, $group->fields->first()->id);
    }

    public function test_can_attach_multiple_fields(): void
    {
        $group = Group::factory()->create();
        $type = Type::factory()->create();
        $field1 = Field::factory()->create(['field_type_id' => $type->id]);
        $field2 = Field::factory()->create(['field_type_id' => $type->id]);
        $group->fields()->attach([$field1->id, $field2->id]);

        $this->assertCount(2, $group->fields);
    }
}
