<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BoardGameControllerTest extends TestCase
{
    // ── GET /api/board-games/search ────────────────────────────────────────────

    public function test_search_requires_term_parameter(): void
    {
        $response = $this->getJson('/api/board-games/search');

        $response->assertStatus(422)->assertJsonValidationErrors(['term']);
    }

    public function test_search_returns_matching_games_by_name_substring(): void
    {
        $response = $this->getJson('/api/board-games/search?term=chess');

        $response->assertOk();
        $items = $response->json('items');
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertStringContainsStringIgnoringCase('chess', $item['name']);
        }
    }

    public function test_search_is_case_insensitive(): void
    {
        $lower = $this->getJson('/api/board-games/search?term=monopoly')->json('items');
        $upper = $this->getJson('/api/board-games/search?term=MONOPOLY')->json('items');
        $mixed = $this->getJson('/api/board-games/search?term=Monopoly')->json('items');

        $this->assertSame(
            array_column($lower, 'id'),
            array_column($upper, 'id'),
        );
        $this->assertSame(
            array_column($lower, 'id'),
            array_column($mixed, 'id'),
        );
    }

    public function test_search_returns_empty_items_array_when_no_match(): void
    {
        $response = $this->getJson('/api/board-games/search?term=xyzzy_no_match_ever');

        $response->assertOk()->assertJson(['items' => []]);
    }

    public function test_search_limits_results_to_ten(): void
    {
        // "a" should match many games (Catan, Cards Against Humanity, etc.)
        $response = $this->getJson('/api/board-games/search?term=a');

        $response->assertOk();
        $this->assertLessThanOrEqual(10, count($response->json('items')));
    }

    public function test_search_returns_correct_item_structure(): void
    {
        $response = $this->getJson('/api/board-games/search?term=chess');

        $response->assertOk();
        $item = $response->json('items.0');
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('category', $item);
        $this->assertArrayHasKey('year', $item);
        $this->assertArrayHasKey('image_url', $item);
    }

    public function test_search_returns_category_and_year_for_known_game(): void
    {
        $response = $this->getJson('/api/board-games/search?term=Monopoly');

        $response->assertOk();
        $item = $response->json('items.0');
        $this->assertSame('Monopoly', $item['name']);
        $this->assertSame('Family', $item['category']);
        $this->assertSame(1935, $item['year']);
    }

    public function test_search_validates_term_max_length(): void
    {
        $response = $this->getJson('/api/board-games/search?term=' . str_repeat('a', 256));

        $response->assertStatus(422)->assertJsonValidationErrors(['term']);
    }

    public function test_search_caches_board_games_list_after_first_call(): void
    {
        Cache::flush();
        $this->assertFalse(Cache::has('board_games_list'));

        $this->getJson('/api/board-games/search?term=chess');

        $this->assertTrue(Cache::has('board_games_list'));
    }

    public function test_search_uses_cache_on_subsequent_calls(): void
    {
        // Prime the cache with a custom list so the second call uses the cache
        Cache::forever('board_games_list', [
            ['id' => 999, 'name' => 'Cached Test Game', 'category' => 'Test', 'year' => 2024, 'image_url' => ''],
        ]);

        $response = $this->getJson('/api/board-games/search?term=cached');

        $response->assertOk();
        $this->assertCount(1, $response->json('items'));
        $this->assertSame('Cached Test Game', $response->json('items.0.name'));
    }

    public function test_search_returns_partial_match_results(): void
    {
        // "catan" should match "Settlers of Catan"
        $response = $this->getJson('/api/board-games/search?term=catan');

        $response->assertOk();
        $names = array_column($response->json('items'), 'name');
        $this->assertContains('Settlers of Catan', $names);
    }

    public function test_search_returns_multiple_results_for_broad_term(): void
    {
        // "s" should match many games
        $response = $this->getJson('/api/board-games/search?term=s');

        $response->assertOk();
        $this->assertGreaterThan(1, count($response->json('items')));
    }
}
