<?php

namespace Tests\Unit\Actions\FieldLayout;

use App\Actions\FieldLayout\CreateNewFieldLayout;
use App\Actions\FieldLayout\DeleteFieldLayout;
use App\Actions\FieldLayout\EditFieldLayout;
use App\Models\FieldLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldLayoutActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_new_field_layout_creates_and_returns_layout(): void
    {
        $action = app(CreateNewFieldLayout::class);
        $layout = $action->create(['name' => 'My Layout', 'handle' => 'my-layout']);

        $this->assertInstanceOf(FieldLayout::class, $layout);
        $this->assertDatabaseHas('field_layouts', ['name' => 'My Layout']);
    }

    public function test_edit_field_layout_updates_name(): void
    {
        $layout = FieldLayout::factory()->create(['name' => 'Old Name', 'handle' => 'old-name']);
        $action = app(EditFieldLayout::class);

        $result = $action->edit($layout, ['name' => 'New Name']);

        $this->assertEquals('New Name', $result->name);
        $this->assertDatabaseHas('field_layouts', ['id' => $layout->id, 'name' => 'New Name']);
    }

    public function test_edit_field_layout_returns_fresh_model(): void
    {
        $layout = FieldLayout::factory()->create(['name' => 'Old Name', 'handle' => 'old-name']);
        $action = app(EditFieldLayout::class);

        $result = $action->edit($layout, ['name' => 'New Name']);

        $this->assertNotSame($layout, $result);
        $this->assertEquals('New Name', $result->name);
    }

    public function test_delete_field_layout_removes_record(): void
    {
        $layout = FieldLayout::factory()->create();
        $action = app(DeleteFieldLayout::class);

        $action->delete($layout);

        $this->assertDatabaseMissing('field_layouts', ['id' => $layout->id]);
    }

    public function test_delete_field_layout_returns_true(): void
    {
        $layout = FieldLayout::factory()->create();
        $action = app(DeleteFieldLayout::class);

        $result = $action->delete($layout);

        $this->assertTrue($result);
    }
}
