<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\PodcastEpisodeEntryType;
use App\Models\Entry;
use App\Models\EntryBehavior;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PodcastEpisodeEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): PodcastEpisodeEntryType
    {
        $record = EntryType::factory()->create(['entry_behavior_id' => EntryBehavior::where('handle', 'podcast-episode')->value('id')]);
        return new PodcastEpisodeEntryType($record);
    }

    // -------------------------------------------------------------------------
    // beforeCreate — episode_number
    // -------------------------------------------------------------------------

    public function test_before_create_assigns_episode_number_when_not_provided(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate([]);

        $this->assertArrayHasKey('episode_number', $result['fields']);
        $this->assertIsInt($result['fields']['episode_number']);
    }

    public function test_before_create_does_not_overwrite_explicit_episode_number(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate(['fields' => ['episode_number' => 42]]);

        $this->assertSame(42, $result['fields']['episode_number']);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate — episode_duration validation
    // -------------------------------------------------------------------------

    public function test_before_update_throws_when_episode_duration_is_zero(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $type->beforeUpdate($entry, ['fields' => ['episode_duration' => 0]]);
    }

    public function test_before_update_throws_when_episode_duration_is_negative(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $type->beforeUpdate($entry, ['fields' => ['episode_duration' => -1]]);
    }

    public function test_before_update_throws_when_episode_duration_is_not_integer(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $type->beforeUpdate($entry, ['fields' => ['episode_duration' => 3.5]]);
    }

    public function test_before_update_passes_when_episode_duration_is_positive_integer(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        $result = $type->beforeUpdate($entry, ['fields' => ['episode_duration' => 3600]]);

        $this->assertSame(3600, $result['fields']['episode_duration']);
    }

    public function test_before_update_passes_when_episode_duration_is_absent(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        $result = $type->beforeUpdate($entry, ['title' => 'Episode Update']);

        $this->assertIsArray($result);
    }
}
