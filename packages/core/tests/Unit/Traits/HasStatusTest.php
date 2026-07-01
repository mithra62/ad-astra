<?php

namespace Tests\Unit\Traits;

use AdAstra\Models\Entry;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use AdAstra\Observers\StatusSyncRegistry;
use AdAstra\Traits\HasStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class HasStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_boot_registers_consumer_class(): void
    {
        // setUp cleared the registry. Entry was already booted by
        // AppServiceProvider, so Laravel's bootIfNotBooted guard would skip
        // bootHasStatus if we just instantiated Entry. Call the boot hook
        // directly to force re-registration.
        Entry::bootHasStatus();

        $this->assertContains(Entry::class, StatusSyncRegistry::consumers());
    }

    // -------------------------------------------------------------------------
    // Registry self-registration
    // -------------------------------------------------------------------------

    public function test_status_relation_returns_belongs_to_status(): void
    {
        $entry = new Entry();
        $this->assertInstanceOf(BelongsTo::class, $entry->status());
        $this->assertInstanceOf(Status::class, $entry->status()->getRelated());
    }

    // -------------------------------------------------------------------------
    // Relations + scopes
    // -------------------------------------------------------------------------

    public function test_public_scope_filters_by_status_is_public(): void
    {
        $group = StatusGroup::factory()->create();
        $draft = Status::factory()->create([
            'status_group_id' => $group->id, 'handle' => 'draft', 'is_public' => false,
        ]);
        $published = Status::factory()->create([
            'status_group_id' => $group->id, 'handle' => 'published', 'is_public' => true,
        ]);

        Entry::factory()->create(['status_id' => $draft->id, 'status_is_public' => false]);
        $publicEntry = Entry::factory()->create([
            'status_id' => $published->id,
            'status_is_public' => true,
        ]);

        $results = Entry::public()->get();

        $this->assertSame(1, $results->count());
        $this->assertSame($publicEntry->id, $results->first()->id);
    }

    public function test_with_status_scope_filters_by_handle(): void
    {
        $group = StatusGroup::factory()->create();
        $draft = Status::factory()->create([
            'status_group_id' => $group->id, 'handle' => 'draft',
        ]);
        $published = Status::factory()->create([
            'status_group_id' => $group->id, 'handle' => 'published',
        ]);

        Entry::factory()->create(['status_id' => $draft->id, 'status_handle' => 'draft']);
        Entry::factory()->create(['status_id' => $published->id, 'status_handle' => 'published']);

        $this->assertSame(1, Entry::withStatus('draft')->count());
        $this->assertSame(1, Entry::withStatus('published')->count());
    }

    public function test_dev_env_contract_check_throws_when_fillable_missing_columns(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/missing column\(s\) from \$fillable/');

        new class () extends Model {
            use HasStatus;

            protected $table = 'test_status_consumer_bad_fillable';

            protected $fillable = ['title']; // missing the triple

            protected $casts = ['status_is_public' => 'boolean'];
        };
    }

    // -------------------------------------------------------------------------
    // Dev-mode contract check (APP_ENV=testing during phpunit)
    // -------------------------------------------------------------------------

    public function test_dev_env_contract_check_throws_when_status_is_public_not_cast_to_bool(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/status_is_public.*expected boolean/');

        new class () extends Model {
            use HasStatus;

            protected $table = 'test_status_consumer_bad_cast';

            protected $fillable = ['status_id', 'status_handle', 'status_is_public'];

            protected $casts = ['status_is_public' => 'integer']; // wrong cast
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Keep the registry isolated between tests so fixture model classes
        // don't bleed into other suites' StatusObserver runs.
        StatusSyncRegistry::clear();
    }
}
