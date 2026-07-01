<?php

namespace Tests\Unit\Models;

use AdAstra\EntryTypes\AbstractEntryType;
use AdAstra\EntryTypes\BlogPostEntryType;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryType;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class EntryBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new EntryBehavior();

        $this->assertEquals(['name', 'handle', 'class', 'description'], $model->getFillable());
    }

    public function test_uses_entry_behaviors_table(): void
    {
        $this->assertEquals('entry_behaviors', (new EntryBehavior())->getTable());
    }

    public function test_entry_types_relationship(): void
    {
        $behavior = EntryBehavior::factory()->create();
        $entryType = EntryType::factory()->create(['entry_behavior_id' => $behavior->id]);

        $this->assertTrue($behavior->entryTypes->contains($entryType));
    }

    public function test_instance_returns_abstract_entry_type_subclass(): void
    {
        $behavior = EntryBehavior::factory()->create(['class' => 'behavior.blog-post']);
        $entryType = EntryType::factory()->create(['entry_behavior_id' => $behavior->id]);

        $instance = $behavior->instance($entryType);

        $this->assertInstanceOf(AbstractEntryType::class, $instance);
        $this->assertInstanceOf(BlogPostEntryType::class, $instance);
    }

    public function test_instance_throws_runtime_exception_for_unregistered_morph_key(): void
    {
        $behavior = EntryBehavior::factory()->create(['class' => 'behavior.unregistered']);
        $entryType = EntryType::factory()->create(['entry_behavior_id' => $behavior->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not registered in the morphMap/');

        $behavior->instance($entryType);
    }

    public function test_instance_throws_runtime_exception_for_nonexistent_class(): void
    {
        Relation::morphMap(['behavior.fake-missing' => 'AdAstra\\Nonexistent\\Behavior']);

        $behavior = EntryBehavior::factory()->create(['class' => 'behavior.fake-missing']);
        $entryType = EntryType::factory()->create(['entry_behavior_id' => $behavior->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $behavior->instance($entryType);
    }

    public function test_instance_throws_runtime_exception_for_class_not_extending_abstract_entry_type(): void
    {
        Relation::morphMap(['behavior.fake-stdclass' => \stdClass::class]);

        $behavior = EntryBehavior::factory()->create(['class' => 'behavior.fake-stdclass']);
        $entryType = EntryType::factory()->create(['entry_behavior_id' => $behavior->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must extend AbstractEntryType/');

        $behavior->instance($entryType);
    }
}
