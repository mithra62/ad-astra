<?php

namespace Tests\Unit\EntryTypes;

use AdAstra\EntryTypes\BlogPostEntryType;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPostEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_before_create_computes_reading_time_from_body(): void
    {
        $type = $this->makeType();
        $body = str_repeat('word ', 400); // 400 words → ceil(400/200) = 2 min

        $result = $type->beforeCreate(['fields' => ['body' => $body]]);

        $this->assertSame(2, $result['fields']['reading_time']);
    }

    // -------------------------------------------------------------------------
    // beforeCreate — reading_time
    // -------------------------------------------------------------------------

    private function makeType(): BlogPostEntryType
    {
        $record = EntryType::factory()->create(['entry_behavior_id' => EntryBehavior::where('handle', 'blog-post')->value('id')]);
        return new BlogPostEntryType($record);
    }

    public function test_before_create_rounds_reading_time_up(): void
    {
        $type = $this->makeType();
        $body = str_repeat('word ', 201); // 201 words → ceil(201/200) = 2 min

        $result = $type->beforeCreate(['fields' => ['body' => $body]]);

        $this->assertSame(2, $result['fields']['reading_time']);
    }

    public function test_before_create_sets_reading_time_to_one_for_short_body(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate(['fields' => ['body' => 'Short post.']]);

        $this->assertSame(1, $result['fields']['reading_time']);
    }

    public function test_before_create_does_not_inject_reading_time_when_body_absent(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate(['fields' => ['excerpt' => 'No body here']]);

        $this->assertArrayNotHasKey('reading_time', $result['fields']);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate — reading_time
    // -------------------------------------------------------------------------

    public function test_before_update_recomputes_reading_time_when_body_changes(): void
    {
        $type = $this->makeType();
        $entry = Entry::factory()->create();
        $body = str_repeat('word ', 600); // 600 → ceil(600/200) = 3 min

        $result = $type->beforeUpdate($entry, ['fields' => ['body' => $body]]);

        $this->assertSame(3, $result['fields']['reading_time']);
    }

    public function test_before_update_does_not_inject_reading_time_when_body_absent(): void
    {
        $type = $this->makeType();
        $entry = Entry::factory()->create();

        $result = $type->beforeUpdate($entry, ['fields' => ['excerpt' => 'Only excerpt']]);

        $this->assertArrayNotHasKey('reading_time', $result['fields']);
    }
}
