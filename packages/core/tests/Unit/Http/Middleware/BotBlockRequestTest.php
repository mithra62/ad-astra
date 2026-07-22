<?php

namespace Tests\Unit\Http\Middleware;

use AdAstra\Http\Middleware\BotBlockRequest;
use AdAstra\Models\BbValue;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The honeypot middleware only interrogates unauthenticated modifying
 * requests: the hidden field name lives in the session, its expected value
 * lives in bb_values, and a valid submission consumes the row so it cannot
 * be replayed.
 */
class BotBlockRequestTest extends TestCase
{
    use RefreshDatabase;

    private function handle(Request $request): Response
    {
        return (new BotBlockRequest())->handle($request, fn ($r) => new Response('ok'));
    }

    public function test_get_requests_pass_through_without_honeypot(): void
    {
        $response = $this->handle(Request::create('/contact', 'GET'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_authenticated_modifying_requests_bypass_the_honeypot(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->handle(Request::create('/contact', 'POST'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_guest_post_without_honeypot_value_is_blocked(): void
    {
        session(['bb_field_name' => '_bb_test_field']);

        $this->expectException(HttpException::class);

        try {
            $this->handle(Request::create('/contact', 'POST'));
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_guest_post_with_wrong_honeypot_value_is_blocked(): void
    {
        session(['bb_field_name' => '_bb_test_field']);
        BbValue::factory()->create(['field_name' => '_bb_test_field']);

        $this->expectException(HttpException::class);

        $this->handle(Request::create('/contact', 'POST', ['_bb_test_field' => 'not-the-value']));
    }

    public function test_guest_post_with_valid_honeypot_value_passes_and_consumes_it(): void
    {
        session(['bb_field_name' => '_bb_test_field']);
        $bb = BbValue::factory()->create(['field_name' => '_bb_test_field']);

        $response = $this->handle(
            Request::create('/contact', 'POST', ['_bb_test_field' => $bb->field_value])
        );

        $this->assertSame('ok', $response->getContent());
        $this->assertDatabaseMissing('bb_values', ['id' => $bb->id]);
    }

    public function test_each_delete_put_and_patch_are_also_guarded(): void
    {
        session(['bb_field_name' => '_bb_test_field']);
        $blocked = 0;

        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            try {
                $this->handle(Request::create('/contact', $method));
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
                $blocked++;
            }
        }

        $this->assertSame(3, $blocked);
    }
}
