<?php

namespace App\Http\Controllers;

use App\Services\DailyActionLimiter;
use App\Services\GameApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class GameController
{
    public function __construct(
        private GameApiClient $api,
        private DailyActionLimiter $limiter,
    ) {}

    /**
     * GET /api/games — list all games sorted by votes descending.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $games = $this->api->listGames();

            usort($games, fn($a, $b) => ($b['votes'] ?? 0) <=> ($a['votes'] ?? 0));

            $games = array_map(function ($game) {
                $game['name'] = html_entity_decode($game['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $steamAppId = Cache::get('steam_app:' . mb_strtolower($game['name']), 0);
                $game['steam_app_id'] = $steamAppId;
                $game['steam_image_url'] = $steamAppId
                    ? "https://cdn.akamai.steamstatic.com/steam/apps/{$steamAppId}/capsule_231x87.jpg"
                    : null;
                return $game;
            }, $games);

            $userId = $request->input('voter_id');
            $todayAction = $userId ? $this->limiter->getTodayAction($userId) : null;

            $lastGameId = 0;
            try {
                $lastGameId = $this->api->getLastGameId();
            } catch (RuntimeException) {
                // Non-fatal: the leaderboard still works without this stat.
            }

            return response()->json([
                'games' => $games,
                'daily_action' => $todayAction,
                'last_game_id' => $lastGameId,
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * POST /api/games — add a new game.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'steam_app_id' => 'nullable|integer',
        ]);

        $name = trim($request->input('name'));
        $steamAppId = $request->integer('steam_app_id') ?: null;
        $userId = $request->input('voter_id');

        if ($this->limiter->hasActedToday($userId)) {
            return response()->json([
                'error' => 'You\'ve already used your daily action. Come back tomorrow!',
            ], 429);
        }

        try {
            $existingGames = $this->api->listGames();
            $duplicate = collect($existingGames)->first(
                fn($game) => mb_strtolower(html_entity_decode($game['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')) === mb_strtolower($name)
            );

            if ($duplicate) {
                $duplicateName = html_entity_decode($duplicate['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return response()->json([
                    'error' => "\"{$duplicateName}\" is already in the leaderboard.",
                ], 422);
            }

            $result = $this->api->addGame($name);
            $gameId = $result['id'] ?? 0;

            if ($steamAppId) {
                Cache::forever('steam_app:' . mb_strtolower($name), $steamAppId);
            }

            $this->limiter->recordAction($userId, 'add', $gameId);

            return response()->json([
                'id' => $gameId,
                'message' => "{$name} has been added to the library!",
            ], 201);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * POST /api/games/{id}/vote — vote for a game.
     */
    public function vote(Request $request, int $id): JsonResponse
    {
        $userId = $request->input('voter_id');

        if ($this->limiter->hasActedToday($userId)) {
            return response()->json([
                'error' => 'You\'ve already used your daily action. Come back tomorrow!',
            ], 429);
        }

        try {
            $this->api->vote($id);
            $this->limiter->recordAction($userId, 'vote', $id);

            return response()->json([
                'message' => 'Vote cast!',
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * DELETE /api/games/{id}/vote — remove a vote.
     */
    public function removeVote(Request $request, int $id): JsonResponse
    {
        $userId = $request->input('voter_id');

        try {
            $this->api->removeVote($id);

            $todayAction = $this->limiter->getTodayAction($userId);
            if ($todayAction && $todayAction['action'] === 'vote' && $todayAction['game_id'] === $id) {
                $this->limiter->clearAction($userId);
            }

            return response()->json([
                'message' => 'Vote removed.',
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * DELETE /api/games/{id} — remove a game from the library.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $request->input('voter_id');

        try {
            $this->api->removeGame($id);

            $todayAction = $this->limiter->getTodayAction($userId);
            if ($todayAction && $todayAction['action'] === 'add' && $todayAction['game_id'] === $id) {
                $this->limiter->clearAction($userId);
            }

            return response()->json([
                'message' => 'Game removed from the library.',
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * GET /api/me — return current user status.
     */
    public function me(Request $request): JsonResponse
    {
        $userId = $request->input('voter_id');

        return response()->json([
            'voter_id' => $userId,
            'daily_action' => $this->limiter->getTodayAction($userId),
        ]);
    }

    /**
     * POST /api/reset — flush all Essense game data and clear local cache.
     * Useful for demos and reviewers who want a clean slate.
     */
    public function reset(): JsonResponse
    {
        try {
            $this->api->flushCache();
            Cache::flush();

            return response()->json(['message' => 'Library reset. All games have been cleared.']);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e);
        }
    }

    private function errorResponse(RuntimeException $e): JsonResponse
    {
        $status = $e->getCode() ?: 502;
        if ($status < 400 || $status > 599) {
            $status = 502;
        }

        return response()->json([
            'error' => $e->getMessage(),
        ], $status);
    }
}
