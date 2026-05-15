<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SteamApiClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.steam.base_url'), '/');
    }

    public function searchGame(string $term): array
    {
        return $this->get('/storesearch', [
            'term' => $term,
            'l'    => 'english',
            'cc'   => 'US',
        ]);
    }

    private function get(string $endpoint, array $query = []): array
    {
        try {
            $response = Http::timeout(10)
                ->withQueryParameters($query)
                ->get($this->baseUrl . $endpoint);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Unable to reach the Steam API. Please try again later.');
        }

        if ($response->failed()) {
            $body = $response->json();
            $message = $body['error'] ?? $body['message'] ?? 'The Steam API returned an error.';
            throw new RuntimeException($message, $response->status());
        }

        return $response->json() ?? [];
    }
}
