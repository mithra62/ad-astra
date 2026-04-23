<?php

namespace Tests\Unit\Models;

use App\Models\UserSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(['field_layout_id'], (new UserSchema)->getFillable());
    }

    public function test_uses_user_schema_table(): void
    {
        $this->assertEquals('user_schema', (new UserSchema)->getTable());
    }

    public function test_resolved_creates_record_with_id_1_on_first_call(): void
    {
        $schema = UserSchema::resolved();

        $this->assertNotNull($schema);
        $this->assertEquals(1, $schema->id);
    }

    public function test_resolved_returns_same_instance_when_called_twice(): void
    {
        $first = UserSchema::resolved();
        $second = UserSchema::resolved();

        $this->assertSame($first, $second);
    }

    public function test_instance_is_alias_for_resolved(): void
    {
        $schema = UserSchema::instance();

        $this->assertEquals(1, $schema->id);
    }

    public function test_flush_resolved_clears_the_static_cache(): void
    {
        $first = UserSchema::resolved();
        UserSchema::flushResolved();
        $second = UserSchema::resolved();

        $this->assertNotSame($first, $second);
        $this->assertEquals($first->id, $second->id);
    }

    public function test_resolved_is_idempotent_on_repeated_calls(): void
    {
        UserSchema::resolved();
        UserSchema::flushResolved();
        UserSchema::resolved();

        // Should exist exactly once (firstOrCreate behaviour)
        $this->assertEquals(1, UserSchema::count());
    }
}
