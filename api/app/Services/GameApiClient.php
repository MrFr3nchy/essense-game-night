<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GameApiClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.essense.base_url'), '/');
        $this->apiKey = config('services.essense.api_key');

        if (empty($this->apiKey)) {
            throw new RuntimeException('ESSENSE_API_KEY is not configured.');
        }
    }

    public function listGames(): array
    {
        return $this->post('/games/list')['games'] ?? [];
    }

    public function addGame(string $name, ?int $steamAppId = null): array
    {
        $params = ['name' => $name];

        if ($steamAppId) {
            $params['steam_app_id'] = $steamAppId;
        }

        return $this->post('/games/add', $params);
    }

    public function searchGame(int $id): array
    {
        return $this->post('/games/search', ['id' => $id]);
    }

    public function removeGame(int $id): array
    {
        return $this->post('/games/remove', ['id' => $id]);
    }

    public function vote(int $id): array
    {
        return $this->post('/games/vote', ['id' => $id]);
    }

    public function removeVote(int $id): array
    {
        return $this->post('/games/removeVote', ['id' => $id]);
    }

    private function post(string $endpoint, array $data = []): array
    {
        $query = array_merge(['api_key' => $this->apiKey], $data);

        try {
            $response = Http::timeout(10)
                ->withQueryParameters($query)
                ->post($this->baseUrl . $endpoint);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Unable to reach the game API. Please try again later.');
        }

        if ($response->failed()) {
            $body = $response->json();
            $message = $body['error'] ?? $body['message'] ?? 'The game API returned an error.';
            throw new RuntimeException($message, $response->status());
        }

        return $response->json() ?? [];
    }
}
