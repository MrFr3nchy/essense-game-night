<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

/**
 * GET /api/health — proxy the Essense challenge API health check.
 *
 * This endpoint requires no authentication; it is safe to call from the
 * frontend before a voter_id cookie is set.
 */
class HealthController
{
    public function __invoke(): JsonResponse
    {
        $baseUrl = rtrim(config('services.essense.base_url'), '/');

        try {
            $response = Http::timeout(5)->get("{$baseUrl}/cache/health");
            $healthy = $response->ok() && ($response->json()[0] ?? null) === 'ok';

            return response()->json(
                ['status' => $healthy ? 'ok' : 'degraded', 'api' => $healthy],
                $healthy ? 200 : 503,
            );
        } catch (ConnectionException) {
            return response()->json(['status' => 'error', 'api' => false], 503);
        }
    }
}
