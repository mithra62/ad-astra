<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\UserStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new UserStatusLog();

        $this->assertEquals([
            'user_id',
            'changed_by_user_id',
            'previous_status',
            'new_status',
            'previous_locked_until',
            'new_locked_until',
            'reason',
            'context',
            'created_at',
        ], $model->getFillable());
    }

    public function test_uses_user_status_logs_table(): void
    {
        $this->assertEquals('user_status_logs', (new UserStatusLog())->getTable());
    }

    public function test_timestamps_are_disabled(): void
    {
        $this->assertFalse((new UserStatusLog())->timestamps);
    }

    public function test_context_is_cast_to_array(): void
    {
        $log = UserStatusLog::factory()->create(['context' => ['ip' => '127.0.0.1']]);

        $this->assertIsArray($log->context);
        $this->assertEquals(['ip' => '127.0.0.1'], $log->context);
    }

    public function test_locked_until_fields_are_cast_to_datetime(): void
    {
        $log = UserStatusLog::factory()->create([
            'previous_locked_until' => '2026-01-01 00:00:00',
            'new_locked_until' => '2026-02-01 00:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $log->previous_locked_until);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $log->new_locked_until);
    }

    public function test_user_relationship(): void
    {
        $user = User::factory()->create();
        $log = UserStatusLog::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($log->user->is($user));
    }

    public function test_actor_relationship(): void
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $log = UserStatusLog::factory()->create([
            'user_id' => $user->id,
            'changed_by_user_id' => $actor->id,
        ]);

        $this->assertTrue($log->actor->is($actor));
        $this->assertFalse($log->actor->is($log->user));
    }
}
