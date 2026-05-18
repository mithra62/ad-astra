<?php

namespace Tests\Unit\Support;

use App\Models\FieldLayout;
use App\Settings;
use App\Support\UserFieldLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFieldLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_returns_null_when_setting_is_not_set(): void
    {
        $this->assertNull(UserFieldLayout::resolve());
    }

    public function test_resolve_returns_null_when_setting_id_does_not_match_any_layout(): void
    {
        app(Settings::class)->set('users', 'user_field_layout_id', 9999, null);

        $this->assertNull(UserFieldLayout::resolve());
    }

    public function test_resolve_returns_field_layout_when_setting_is_set(): void
    {
        $layout = FieldLayout::factory()->create();
        app(Settings::class)->set('users', 'user_field_layout_id', $layout->id, null);

        $resolved = UserFieldLayout::resolve();

        $this->assertInstanceOf(FieldLayout::class, $resolved);
        $this->assertEquals($layout->id, $resolved->id);
    }

    public function test_resolve_eager_loads_tabs(): void
    {
        $layout = FieldLayout::factory()->create();
        app(Settings::class)->set('users', 'user_field_layout_id', $layout->id, null);

        $resolved = UserFieldLayout::resolve();

        $this->assertTrue($resolved->relationLoaded('tabs'));
    }

    public function test_resolved_id_returns_null_when_setting_is_not_set(): void
    {
        $this->assertNull(UserFieldLayout::resolvedId());
    }

    public function test_resolved_id_returns_integer_when_setting_is_set(): void
    {
        $layout = FieldLayout::factory()->create();
        app(Settings::class)->set('users', 'user_field_layout_id', $layout->id, null);

        $this->assertSame($layout->id, UserFieldLayout::resolvedId());
    }
}
