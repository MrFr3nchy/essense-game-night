<?php

namespace App\Http\Controllers;

use App\Services\SteamApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SteamController
{
    public function __construct(private SteamApiClient $steam) {}

    /**
     * GET /api/steam/search?term=Hollow+Knight
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'term' => 'required|string|max:255',
        ]);

        try {
            $results = $this->steam->searchGame($request->input('term'));

            return response()->json($results);
        } catch (RuntimeException $e) {
            $status = $e->getCode();
            if ($status < 400 || $status > 599) {
                $status = 502;
            }

            return response()->json(['error' => $e->getMessage()], $status);
        }
    }
}
