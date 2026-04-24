<?php

namespace Tests\Unit\Actions\FieldLayout\Tab;

use App\Actions\FieldLayout\Tab\CreateNewTab;
use App\Actions\FieldLayout\Tab\DeleteTab;
use App\Actions\FieldLayout\Tab\EditTab;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_new_tab_creates_and_returns_tab(): void
    {
        $layout = FieldLayout::factory()->create();
        $action = app(CreateNewTab::class);

        $tab = $action->create($layout, ['name' => 'My Tab', 'sort_order' => 2]);

        $this->assertInstanceOf(Tab::class, $tab);
        $this->assertDatabaseHas('field_layout_tabs', [
            'field_layout_id' => $layout->id,
            'name' => 'My Tab',
            'sort_order' => 2,
        ]);
    }

    public function test_create_new_tab_defaults_sort_order_to_zero(): void
    {
        $layout = FieldLayout::factory()->create();
        $action = app(CreateNewTab::class);

        $tab = $action->create($layout, ['name' => 'My Tab']);

        $this->assertEquals(0, $tab->sort_order);
    }

    public function test_create_new_tab_belongs_to_layout(): void
    {
        $layout = FieldLayout::factory()->create();
        $action = app(CreateNewTab::class);

        $tab = $action->create($layout, ['name' => 'My Tab']);

        $this->assertEquals($layout->id, $tab->field_layout_id);
    }

    public function test_edit_tab_updates_name_and_sort_order(): void
    {
        $tab = Tab::factory()->create(['name' => 'Old Name', 'sort_order' => 1]);
        $action = app(EditTab::class);

        $result = $action->edit($tab, ['name' => 'New Name', 'sort_order' => 5]);

        $this->assertEquals('New Name', $result->name);
        $this->assertEquals(5, $result->sort_order);
        $this->assertDatabaseHas('field_layout_tabs', ['id' => $tab->id, 'name' => 'New Name', 'sort_order' => 5]);
    }

    public function test_edit_tab_defaults_sort_order_to_zero_when_omitted(): void
    {
        $tab = Tab::factory()->create(['name' => 'Old Name', 'sort_order' => 3]);
        $action = app(EditTab::class);

        $result = $action->edit($tab, ['name' => 'New Name']);

        $this->assertEquals(0, $result->sort_order);
    }

    public function test_edit_tab_returns_fresh_model(): void
    {
        $tab = Tab::factory()->create(['name' => 'Old Name']);
        $action = app(EditTab::class);

        $result = $action->edit($tab, ['name' => 'New Name']);

        $this->assertNotSame($tab, $result);
        $this->assertEquals('New Name', $result->name);
    }

    public function test_delete_tab_removes_record(): void
    {
        $tab = Tab::factory()->create();
        $action = app(DeleteTab::class);

        $action->delete($tab);

        $this->assertDatabaseMissing('field_layout_tabs', ['id' => $tab->id]);
    }

    public function test_delete_tab_returns_true(): void
    {
        $tab = Tab::factory()->create();
        $action = app(DeleteTab::class);

        $result = $action->delete($tab);

        $this->assertTrue($result);
    }
}
