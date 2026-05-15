<?php

namespace Tests\Feature;

use App\Services\GameApiClient;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentifyUserMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $mock = $this->mock(GameApiClient::class);
        $mock->shouldReceive('listGames')->andReturn([]);
    }

    // ── Cookie assignment ──────────────────────────────────────────────────────

    public function test_middleware_sets_voter_id_cookie_when_none_present(): void
    {
        $response = $this->getJson('/api/games');

        $response->assertOk();
        $cookie = collect($response->headers->getCookies())
            ->first(fn($c) => $c->getName() === 'voter_id');

        $this->assertNotNull($cookie, 'Expected voter_id cookie to be set');
        $this->assertNotEmpty($cookie->getValue());
    }

    public function test_middleware_voter_id_cookie_is_a_valid_uuid(): void
    {
        $response = $this->getJson('/api/games');

        $cookie = collect($response->headers->getCookies())
            ->first(fn($c) => $c->getName() === 'voter_id');

        $this->assertTrue(
            Str::isUuid($cookie->getValue()),
            "Expected voter_id to be a UUID, got: {$cookie->getValue()}"
        );
    }

    public function test_middleware_voter_id_in_response_cookie_matches_controller_input(): void
    {
        // Whatever voter_id the middleware sets on the response cookie should be
        // exactly the same value that it merges into the request (and thus returns via /api/me).
        $response = $this->getJson('/api/me');

        $cookie = collect($response->headers->getCookies())
            ->first(fn($c) => $c->getName() === 'voter_id');

        $this->assertNotNull($cookie);
        $this->assertSame($cookie->getValue(), $response->json('voter_id'));
    }

    public function test_middleware_generates_consistent_voter_id_within_same_request(): void
    {
        // The voter_id in the response body (/api/me) and in the response cookie
        // must be the same UUID — the middleware must not generate two different IDs.
        $response = $this->getJson('/api/me');

        $cookie = collect($response->headers->getCookies())
            ->first(fn($c) => $c->getName() === 'voter_id');

        $this->assertTrue(Str::isUuid($cookie->getValue()));
        $this->assertTrue(Str::isUuid($response->json('voter_id')));
        $this->assertSame($cookie->getValue(), $response->json('voter_id'));
    }

    public function test_middleware_assigns_different_id_to_each_new_visitor(): void
    {
        $response1 = $this->getJson('/api/games');
        $response2 = $this->getJson('/api/games');

        $id1 = collect($response1->headers->getCookies())
            ->first(fn($c) => $c->getName() === 'voter_id')?->getValue();
        $id2 = collect($response2->headers->getCookies())
            ->first(fn($c) => $c->getName() === 'voter_id')?->getValue();

        $this->assertNotSame($id1, $id2);
    }

    public function test_middleware_cookie_has_long_lifetime(): void
    {
        $response = $this->getJson('/api/games');

        $cookie = collect($response->headers->getCookies())
            ->first(fn($c) => $c->getName() === 'voter_id');

        // Should expire at least 1 year from now
        $oneYearFromNow = time() + (60 * 60 * 24 * 365);
        $this->assertGreaterThanOrEqual($oneYearFromNow, $cookie->getExpiresTime());
    }

    public function test_middleware_applies_to_games_routes(): void
    {
        $this->getJson('/api/games')->assertOk();
    }

    public function test_middleware_applies_to_me_route(): void
    {
        $this->getJson('/api/me')->assertOk();
    }
}
