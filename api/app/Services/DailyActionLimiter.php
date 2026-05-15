<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class DailyActionLimiter
{
    /**
     * Check whether the user has already performed their daily action.
     * Each user gets ONE action per calendar day: either adding a game or voting.
     */
    public function hasActedToday(?string $userId): bool
    {
        if (!$userId || config('services.app.allow_unlimited_actions')) {
            return false;
        }

        return Cache::has($this->cacheKey($userId));
    }

    /**
     * Record that the user performed an action (add or vote).
     */
    public function recordAction(?string $userId, string $actionType, int $gameId): void
    {
        if (!$userId) return;

        Cache::put($this->cacheKey($userId), [
            'action' => $actionType,
            'game_id' => $gameId,
            'at' => now()->toIso8601String(),
        ], now()->endOfDay());
    }

    /**
     * Get the action the user performed today, if any.
     */
    public function getTodayAction(?string $userId): ?array
    {
        if (!$userId || config('services.app.allow_unlimited_actions')) {
            return null;
        }

        return Cache::get($this->cacheKey($userId));
    }

    /**
     * Clear the user's daily action (e.g. when undoing a vote or removing an added game).
     */
    public function clearAction(?string $userId): void
    {
        if (!$userId) return;

        Cache::forget($this->cacheKey($userId));
    }

    private function cacheKey(string $userId): string
    {
        $date = now()->format('Y-m-d');
        return "daily_action:{$userId}:{$date}";
    }

}
