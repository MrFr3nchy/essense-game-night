<?php

namespace Tests\Unit;

use App\Services\GameApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameApiClientTest extends TestCase
{
    private GameApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.essense.base_url' => 'https://codechallenge.essensedesigns.info']);
        config(['services.essense.api_key' => 'test-api-key']);
        $this->client = new GameApiClient();
    }

    // ── listGames ──────────────────────────────────────────────────────────────

    public function test_list_games_returns_games_array(): void
    {
        Http::fake([
            '*/games/list*' => Http::response(['games' => [
                ['id' => 1, 'name' => 'Hollow Knight', 'votes' => 5],
            ]], 200),
        ]);

        $games = $this->client->listGames();

        $this->assertCount(1, $games);
        $this->assertSame('Hollow Knight', $games[0]['name']);
    }

    public function test_list_games_returns_empty_array_when_no_games_key(): void
    {
        Http::fake(['*/games/list*' => Http::response([], 200)]);

        $games = $this->client->listGames();

        $this->assertSame([], $games);
    }

    public function test_list_games_sends_api_key_as_query_parameter(): void
    {
        Http::fake(['*/games/list*' => Http::response(['games' => []], 200)]);

        $this->client->listGames();

        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'api_key=test-api-key'));
    }

    public function test_list_games_uses_post_method(): void
    {
        Http::fake(['*/games/list*' => Http::response(['games' => []], 200)]);

        $this->client->listGames();

        Http::assertSent(fn(Request $r) => $r->method() === 'POST');
    }

    // ── addGame ────────────────────────────────────────────────────────────────

    public function test_add_game_sends_name_as_query_parameter(): void
    {
        Http::fake(['*/games/add*' => Http::response(['id' => 10, 'name' => 'Hades'], 200)]);

        $this->client->addGame('Hades');

        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'name=Hades'));
    }

    public function test_add_game_does_not_send_steam_app_id_to_essense_api(): void
    {
        Http::fake(['*/games/add*' => Http::response(['id' => 10], 200)]);

        $this->client->addGame('Hades');

        Http::assertSent(function (Request $request) {
            return !array_key_exists('steam_app_id', $request->data());
        });
    }

    public function test_add_game_returns_response_body(): void
    {
        Http::fake(['*/games/add*' => Http::response(['id' => 42, 'name' => 'Hades'], 200)]);

        $result = $this->client->addGame('Hades');

        $this->assertSame(42, $result['id']);
        $this->assertSame('Hades', $result['name']);
    }

    // ── vote ───────────────────────────────────────────────────────────────────

    public function test_vote_sends_correct_game_id_as_query_parameter(): void
    {
        Http::fake(['*/games/vote*' => Http::response(['success' => true], 200)]);

        $this->client->vote(7);

        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'id=7'));
    }

    // ── removeVote ─────────────────────────────────────────────────────────────

    public function test_remove_vote_sends_correct_game_id_as_query_parameter(): void
    {
        Http::fake(['*/games/removeVote*' => Http::response([], 200)]);

        $this->client->removeVote(7);

        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'id=7'));
    }

    // ── removeGame ─────────────────────────────────────────────────────────────

    public function test_remove_game_sends_correct_game_id_as_query_parameter(): void
    {
        Http::fake(['*/games/remove*' => Http::response([], 200)]);

        $this->client->removeGame(12);

        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'id=12'));
    }

    // ── searchGame ─────────────────────────────────────────────────────────────

    public function test_search_game_sends_correct_id_as_query_parameter(): void
    {
        Http::fake(['*/games/search*' => Http::response(['id' => 5, 'name' => 'Celeste'], 200)]);

        $result = $this->client->searchGame(5);

        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'id=5'));
        $this->assertSame('Celeste', $result['name']);
    }

    // ── getLastGameId ──────────────────────────────────────────────────────────

    public function test_get_last_game_id_returns_id_from_response(): void
    {
        Http::fake(['*/lastGameId*' => Http::response(['id' => 42], 200)]);

        $id = $this->client->getLastGameId();

        $this->assertSame(42, $id);
    }

    public function test_get_last_game_id_sends_api_key_as_query_parameter(): void
    {
        Http::fake(['*/lastGameId*' => Http::response(['id' => 1], 200)]);

        $this->client->getLastGameId();

        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'api_key=test-api-key'));
    }

    public function test_get_last_game_id_uses_get_method(): void
    {
        Http::fake(['*/lastGameId*' => Http::response(['id' => 1], 200)]);

        $this->client->getLastGameId();

        Http::assertSent(fn(Request $r) => $r->method() === 'GET');
    }

    public function test_get_last_game_id_returns_zero_when_id_missing(): void
    {
        Http::fake(['*/lastGameId*' => Http::response([], 200)]);

        $this->assertSame(0, $this->client->getLastGameId());
    }

    // ── flushCache ─────────────────────────────────────────────────────────────

    public function test_flush_cache_returns_true_on_success(): void
    {
        Http::fake(['*/cache/flush*' => Http::response(['success' => true], 200)]);

        $this->assertTrue($this->client->flushCache());
    }

    public function test_flush_cache_sends_api_key_as_query_parameter(): void
    {
        Http::fake(['*/cache/flush*' => Http::response(['success' => true], 200)]);

        $this->client->flushCache();

        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'api_key=test-api-key'));
    }

    public function test_flush_cache_returns_false_when_success_not_true(): void
    {
        Http::fake(['*/cache/flush*' => Http::response([], 200)]);

        $this->assertFalse($this->client->flushCache());
    }

    // ── Error handling ─────────────────────────────────────────────────────────

    public function test_throws_runtime_exception_on_http_error_response(): void
    {
        Http::fake(['*/games/list*' => Http::response(['error' => 'Unauthorized'], 401)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized');
        $this->expectExceptionCode(401);

        $this->client->listGames();
    }

    public function test_throws_runtime_exception_with_502_on_connection_failure(): void
    {
        Http::fake(['*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        }]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to reach the game API. Please try again later.');

        $this->client->listGames();
    }

    public function test_throws_with_message_from_error_field_in_response(): void
    {
        Http::fake(['*/games/add*' => Http::response(['error' => 'Game already exists'], 422)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Game already exists');

        $this->client->addGame('Duplicate');
    }

    public function test_throws_with_message_from_message_field_when_no_error_field(): void
    {
        Http::fake(['*/games/list*' => Http::response(['message' => 'Rate limited'], 429)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rate limited');

        $this->client->listGames();
    }

    public function test_throws_runtime_exception_when_api_key_not_configured(): void
    {
        config(['services.essense.api_key' => '']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ESSENSE_API_KEY is not configured.');

        new GameApiClient();
    }

    // ── All requests include api_key in body ───────────────────────────────────

    public function test_all_requests_include_api_key_as_query_parameter(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $this->client->vote(1);
        $this->client->removeVote(1);
        $this->client->removeGame(1);

        Http::assertSentCount(3);
        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'api_key=test-api-key'));
    }
}
