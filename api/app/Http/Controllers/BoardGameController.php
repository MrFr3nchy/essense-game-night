<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class BoardGameController
{
    /**
     * GET /api/board-games/search?term=chess
     * Case-insensitive substring search against the cached board_games.json list.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'term' => 'required|string|max:255',
        ]);

        $term = mb_strtolower(trim($request->input('term')));

        $all = Cache::rememberForever('board_games_list', function () {
            $path = database_path('board_games.json');
            $data = json_decode(File::get($path), true);
            return $data['board_games'] ?? [];
        });

        $matches = array_values(array_filter(
            $all,
            fn($game) => str_contains(mb_strtolower($game['name']), $term)
        ));

        $matches = array_map(function ($game) {
            if (!empty($game['image_url']) && preg_match('/(pic\d+\.jpg)$/i', $game['image_url'], $m)) {
                $game['image_url'] = 'https://cf.geekdo-images.com/' . $m[1];
            }
            return $game;
        }, $matches);

        return response()->json([
            'items' => array_slice($matches, 0, 10),
        ]);
    }
}
