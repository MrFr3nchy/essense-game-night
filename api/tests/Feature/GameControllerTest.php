<?php

namespace Tests\Feature;

use App\Http\Middleware\IdentifyUser;
use App\Services\DailyActionLimiter;
use App\Services\GameApiClient;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    private const VOTER = 'test-voter-uuid';

    protected function setUp(): void
    {
        parent::setUp();
        // Test controller logic in isolation from the IdentifyUser middleware.
        // voter_id is passed directly in request body / query string instead.
        $this->withoutMiddleware(IdentifyUser::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function mockApi(): \Mockery\MockInterface
    {
        return $this->mock(GameApiClient::class);
    }

    private function stubGames(array $games): \Mockery\MockInterface
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('listGames')->andReturn($games);
        return $mock;
    }

    private function sampleGames(): array
    {
        return [
            ['id' => 1, 'name' => 'Baldur\'s Gate 3', 'votes' => 10],
            ['id' => 2, 'name' => 'Hollow Knight', 'votes' => 3],
            ['id' => 3, 'name' => 'Celeste', 'votes' => 7],
        ];
    }

    // ── GET /api/games ─────────────────────────────────────────────────────────

    public function test_index_returns_games_sorted_by_votes_descending(): void
    {
        $this->stubGames($this->sampleGames());

        $response = $this->getJson('/api/games?voter_id=' . self::VOTER);

        $response->assertOk();
        $names = collect($response->json('games'))->pluck('name')->all();
        $this->assertSame(["Baldur's Gate 3", 'Celeste', 'Hollow Knight'], $names);
    }

    public function test_index_enriches_games_with_steam_image_url(): void
    {
        Cache::forever('steam_app:hollow knight', 367520);
        $this->stubGames([['id' => 1, 'name' => 'Hollow Knight', 'votes' => 1]]);

        $response = $this->getJson('/api/games?voter_id=' . self::VOTER);

        $game = $response->json('games.0');
        $this->assertSame(367520, $game['steam_app_id']);
        $this->assertStringContainsString('367520', $game['steam_image_url']);
    }

    public function test_index_sets_steam_image_url_null_for_games_without_steam_id(): void
    {
        $this->stubGames([['id' => 1, 'name' => 'Unknown Game', 'votes' => 0]]);

        $response = $this->getJson('/api/games?voter_id=' . self::VOTER);

        $game = $response->json('games.0');
        $this->assertSame(0, $game['steam_app_id']);
        $this->assertNull($game['steam_image_url']);
    }

    public function test_index_decodes_html_entities_in_game_names(): void
    {
        $this->stubGames([['id' => 1, 'name' => 'Overwatch&reg;', 'votes' => 1]]);

        $response = $this->getJson('/api/games?voter_id=' . self::VOTER);

        $this->assertSame('Overwatch®', $response->json('games.0.name'));
    }

    public function test_index_returns_502_when_api_client_throws(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('listGames')->andThrow(new \RuntimeException('Service unavailable', 502));

        $response = $this->getJson('/api/games?voter_id=' . self::VOTER);

        $response->assertStatus(502)->assertJsonFragment(['error' => 'Service unavailable']);
    }

    // ── POST /api/games ────────────────────────────────────────────────────────

    public function test_store_adds_game_successfully(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('listGames')->once()->andReturn([]);
        $mock->shouldReceive('addGame')->once()->with('Hades', null)->andReturn(['id' => 10]);

        $response = $this->postJson('/api/games', [
            'voter_id' => self::VOTER,
            'name' => 'Hades',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['id' => 10])
            ->assertJsonFragment(['message' => 'Hades has been added to the library!']);
    }

    public function test_store_records_daily_action_after_successful_add(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('listGames')->andReturn([]);
        $mock->shouldReceive('addGame')->andReturn(['id' => 10]);

        $this->postJson('/api/games', ['voter_id' => self::VOTER, 'name' => 'Hades']);

        $this->assertTrue(app(DailyActionLimiter::class)->hasActedToday(self::VOTER));
    }

    public function test_store_caches_steam_app_id_when_provided(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('listGames')->andReturn([]);
        $mock->shouldReceive('addGame')->with('Hades', 1145360)->andReturn(['id' => 10]);

        $this->postJson('/api/games', [
            'voter_id' => self::VOTER,
            'name' => 'Hades',
            'steam_app_id' => 1145360,
        ]);

        $this->assertSame(1145360, Cache::get('steam_app:hades'));
    }

    public function test_store_returns_429_when_daily_action_already_used(): void
    {
        $this->mockApi();
        app(DailyActionLimiter::class)->recordAction(self::VOTER, 'vote', 1);

        $response = $this->postJson('/api/games', [
            'voter_id' => self::VOTER,
            'name' => 'New Game',
        ]);

        $response->assertStatus(429)
            ->assertJsonFragment(['error' => "You've already used your daily action. Come back tomorrow!"]);
    }

    public function test_store_returns_422_for_duplicate_game_name(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('listGames')->andReturn([
            ['id' => 1, 'name' => 'Hades', 'votes' => 5],
        ]);

        $response = $this->postJson('/api/games', [
            'voter_id' => self::VOTER,
            'name' => 'hades',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['error' => '"Hades" is already in the leaderboard.']);
    }

    public function test_store_validates_that_name_is_required(): void
    {
        $this->mockApi();

        $response = $this->postJson('/api/games', ['voter_id' => self::VOTER]);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_store_returns_502_when_api_client_throws(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('listGames')->andReturn([]);
        $mock->shouldReceive('addGame')->andThrow(new \RuntimeException('API down', 502));

        $response = $this->postJson('/api/games', [
            'voter_id' => self::VOTER,
            'name' => 'New Game',
        ]);

        $response->assertStatus(502)->assertJsonFragment(['error' => 'API down']);
    }

    // ── POST /api/games/{id}/vote ──────────────────────────────────────────────

    public function test_vote_casts_vote_and_returns_success(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('vote')->once()->with(42)->andReturn([]);

        $response = $this->postJson('/api/games/42/vote', ['voter_id' => self::VOTER]);

        $response->assertOk()->assertJsonFragment(['message' => 'Vote cast!']);
    }

    public function test_vote_records_daily_action(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('vote')->andReturn([]);

        $this->postJson('/api/games/42/vote', ['voter_id' => self::VOTER]);

        $limiter = app(DailyActionLimiter::class);
        $this->assertTrue($limiter->hasActedToday(self::VOTER));
        $action = $limiter->getTodayAction(self::VOTER);
        $this->assertSame('vote', $action['action']);
        $this->assertSame(42, $action['game_id']);
    }

    public function test_vote_returns_429_when_daily_action_used(): void
    {
        $this->mockApi();
        app(DailyActionLimiter::class)->recordAction(self::VOTER, 'add', 5);

        $response = $this->postJson('/api/games/42/vote', ['voter_id' => self::VOTER]);

        $response->assertStatus(429);
    }

    public function test_vote_returns_502_when_api_client_throws(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('vote')->andThrow(new \RuntimeException('Game not found', 404));

        $response = $this->postJson('/api/games/999/vote', ['voter_id' => self::VOTER]);

        $response->assertStatus(404)->assertJsonFragment(['error' => 'Game not found']);
    }

    // ── DELETE /api/games/{id}/vote ────────────────────────────────────────────

    public function test_remove_vote_removes_vote_and_returns_success(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('removeVote')->once()->with(42)->andReturn([]);

        $response = $this->deleteJson('/api/games/42/vote', ['voter_id' => self::VOTER]);

        $response->assertOk()->assertJsonFragment(['message' => 'Vote removed.']);
    }

    public function test_remove_vote_clears_daily_action_when_it_was_for_that_game(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('removeVote')->andReturn([]);

        $limiter = app(DailyActionLimiter::class);
        $limiter->recordAction(self::VOTER, 'vote', 42);

        $this->deleteJson('/api/games/42/vote', ['voter_id' => self::VOTER]);

        $this->assertFalse($limiter->hasActedToday(self::VOTER));
    }

    public function test_remove_vote_does_not_clear_daily_action_for_different_game(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('removeVote')->andReturn([]);

        $limiter = app(DailyActionLimiter::class);
        $limiter->recordAction(self::VOTER, 'vote', 99);

        $this->deleteJson('/api/games/42/vote', ['voter_id' => self::VOTER]);

        $this->assertTrue($limiter->hasActedToday(self::VOTER));
    }

    // ── DELETE /api/games/{id} ─────────────────────────────────────────────────

    public function test_destroy_removes_game_and_returns_success(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('removeGame')->once()->with(7)->andReturn([]);

        $response = $this->deleteJson('/api/games/7', ['voter_id' => self::VOTER]);

        $response->assertOk()->assertJsonFragment(['message' => 'Game removed from the library.']);
    }

    public function test_destroy_clears_daily_action_when_user_added_that_game_today(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('removeGame')->andReturn([]);

        $limiter = app(DailyActionLimiter::class);
        $limiter->recordAction(self::VOTER, 'add', 7);

        $this->deleteJson('/api/games/7', ['voter_id' => self::VOTER]);

        $this->assertFalse($limiter->hasActedToday(self::VOTER));
    }

    public function test_destroy_returns_502_when_api_client_throws(): void
    {
        $mock = $this->mockApi();
        $mock->shouldReceive('removeGame')->andThrow(new \RuntimeException('Not found', 404));

        $response = $this->deleteJson('/api/games/999', ['voter_id' => self::VOTER]);

        $response->assertStatus(404);
    }

    // ── GET /api/me ────────────────────────────────────────────────────────────

    public function test_me_returns_voter_id_and_null_daily_action_when_no_action(): void
    {
        $this->mockApi();

        $response = $this->getJson('/api/me?voter_id=' . self::VOTER);

        $response->assertOk()
            ->assertJsonFragment(['voter_id' => self::VOTER])
            ->assertJsonFragment(['daily_action' => null]);
    }

    public function test_me_returns_daily_action_after_user_votes(): void
    {
        $this->mockApi();
        app(DailyActionLimiter::class)->recordAction(self::VOTER, 'vote', 3);

        $response = $this->getJson('/api/me?voter_id=' . self::VOTER);

        $action = $response->json('daily_action');
        $this->assertSame('vote', $action['action']);
        $this->assertSame(3, $action['game_id']);
    }

}
