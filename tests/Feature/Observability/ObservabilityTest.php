<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Modules\Identity\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ObservabilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function health_endpoint_answers_without_authentication(): void
    {
        $this->get('/up')->assertOk();
    }

    #[Test]
    public function every_response_carries_correlation_ids(): void
    {
        $response = $this->getJson('/api/v1/me')->assertUnauthorized();

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $response->headers->get('X-Request-Id'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $response->headers->get('X-Trace-Id'));
    }

    #[Test]
    public function an_incoming_request_id_and_w3c_trace_are_propagated(): void
    {
        $traceId = '4bf92f3577b34da6a3ce929d0e0e4736';

        $this->withHeaders([
            'X-Request-Id' => 'gateway-123',
            'traceparent' => "00-{$traceId}-00f067aa0ba902b7-01",
        ])->getJson('/api/v1/me')
            ->assertHeader('X-Request-Id', 'gateway-123')
            ->assertHeader('X-Trace-Id', $traceId);
    }

    #[Test]
    public function an_unsafe_request_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Request-Id', "evil\nheader")->getJson('/api/v1/me');

        $this->assertNotSame("evil\nheader", $response->headers->get('X-Request-Id'));
    }

    #[Test]
    public function tenant_and_user_are_added_to_the_log_context(): void
    {
        $tenant = $this->createTenant('estudio-lotus');
        $user = $this->createUser($tenant, Role::Student);

        $this->actingAsJwt($user)->getJson('/api/v1/me')->assertOk();

        $this->assertSame($tenant->id, Context::get('tenant_id'));
        $this->assertSame($user->id, Context::get('user_id'));
    }

    #[Test]
    public function metrics_expose_request_counts_by_route_template(): void
    {
        $this->getJson('/api/v1/sessions/01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertUnauthorized();

        $this->get('/metrics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
            ->assertSee('saas_http_requests_total{method="GET",route="api/v1/sessions/{session}",status="401"} 1', false)
            ->assertSee('saas_http_request_duration_seconds_bucket', false);
    }
}
