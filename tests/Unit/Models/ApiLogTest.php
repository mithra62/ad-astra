<?php

namespace Tests\Unit\Models;

use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_log_has_fillable_attributes(): void
    {
        $apiLog = new ApiLog();
        $fillable = [
            'request_route',
            'method',
            'request_payload',
            'request_headers',
            'response_payload',
            'response_headers',
            'response_status_code',
            'user_id',
        ];
        $this->assertEquals($fillable, $apiLog->getFillable());
    }

    public function test_api_log_has_correct_table_name(): void
    {
        $apiLog = new ApiLog();
        $this->assertEquals('api_logs', $apiLog->getTable());
    }

    public function test_api_log_belongs_to_user(): void
    {
        $apiLog = new ApiLog();
        $this->assertInstanceOf(BelongsTo::class, $apiLog->user());
        $this->assertInstanceOf(User::class, $apiLog->user()->getRelated());
    }

    public function test_api_log_can_be_created_via_factory(): void
    {
        $user = User::factory()->create();
        $apiLog = ApiLog::factory()->create([
            'user_id' => $user->id,
            'method' => 'POST',
            'response_status_code' => 201,
        ]);

        $this->assertDatabaseHas('api_logs', [
            'id' => $apiLog->id,
            'user_id' => $user->id,
            'method' => 'POST',
            'response_status_code' => 201,
        ]);
    }

    public function test_api_log_user_relationship_is_accessible(): void
    {
        $apiLog = ApiLog::factory()->create();

        $this->assertInstanceOf(User::class, $apiLog->user);
        $this->assertEquals($apiLog->user_id, $apiLog->user->id);
    }
}
