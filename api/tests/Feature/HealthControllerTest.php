<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    public function test_health_returns_ok_when_essense_api_is_healthy(): void
    {
        Http::fake(['*/cache/health*' => Http::response(['ok'], 200)]);

        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson(['status' => 'ok', 'api' => true]);
    }

    public function test_health_returns_503_when_essense_api_is_unhealthy(): void
    {
        Http::fake(['*/cache/health*' => Http::response(['error'], 503)]);

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson(['status' => 'degraded', 'api' => false]);
    }

    public function test_health_returns_503_on_connection_failure(): void
    {
        Http::fake(['*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        }]);

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson(['status' => 'error', 'api' => false]);
    }

    public function test_health_requires_no_authentication(): void
    {
        Http::fake(['*/cache/health*' => Http::response(['ok'], 200)]);

        // No voter_id cookie or param — should still succeed.
        $response = $this->getJson('/api/health');

        $response->assertOk();
    }
}
