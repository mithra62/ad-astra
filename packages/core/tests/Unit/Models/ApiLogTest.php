<?php

namespace Tests\Unit\Models;

use AdAstra\Models\ApiLog;
use AdAstra\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new ApiLog;

        $this->assertEquals(
            [
                'request_route',
                'method',
                'request_payload',
                'request_headers',
                'response_headers',
                'response_status_code',
                'user_id',
            ],
            $model->getFillable()
        );
    }

    public function test_uses_api_logs_table(): void
    {
        $this->assertEquals('api_logs', (new ApiLog)->getTable());
    }

    public function test_user_relationship_is_belongs_to(): void
    {
        $log = ApiLog::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $log->user());
    }

    public function test_user_relationship_returns_associated_user(): void
    {
        $user = User::factory()->create();
        $log = ApiLog::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $log->user->id);
    }

    public function test_user_id_accepts_a_valid_user(): void
    {
        $user = User::factory()->create();
        $log = ApiLog::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $log->user_id);
    }

    public function test_user_id_is_nullable_for_unauthenticated_requests(): void
    {
        $log = ApiLog::factory()->create(['user_id' => null]);

        $this->assertNull($log->fresh()->user_id);
    }
}
