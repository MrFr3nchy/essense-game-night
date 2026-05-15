<?php

namespace Tests\Unit;

use App\Services\DailyActionLimiter;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DailyActionLimiterTest extends TestCase
{
    private DailyActionLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limiter = new DailyActionLimiter();
    }

    // ── hasActedToday ──────────────────────────────────────────────────────────

    public function test_has_not_acted_today_when_no_action_recorded(): void
    {
        $this->assertFalse($this->limiter->hasActedToday('user-abc'));
    }

    public function test_has_acted_today_after_recording_a_vote(): void
    {
        $this->limiter->recordAction('user-abc', 'vote', 42);

        $this->assertTrue($this->limiter->hasActedToday('user-abc'));
    }

    public function test_has_acted_today_after_recording_an_add(): void
    {
        $this->limiter->recordAction('user-abc', 'add', 99);

        $this->assertTrue($this->limiter->hasActedToday('user-abc'));
    }

    public function test_different_users_have_independent_daily_actions(): void
    {
        $this->limiter->recordAction('user-1', 'vote', 10);

        $this->assertTrue($this->limiter->hasActedToday('user-1'));
        $this->assertFalse($this->limiter->hasActedToday('user-2'));
    }

    // ── getTodayAction ─────────────────────────────────────────────────────────

    public function test_get_today_action_returns_null_when_no_action(): void
    {
        $this->assertNull($this->limiter->getTodayAction('user-abc'));
    }

    public function test_get_today_action_returns_correct_data_after_vote(): void
    {
        $this->limiter->recordAction('user-abc', 'vote', 7);

        $action = $this->limiter->getTodayAction('user-abc');

        $this->assertIsArray($action);
        $this->assertSame('vote', $action['action']);
        $this->assertSame(7, $action['game_id']);
        $this->assertArrayHasKey('at', $action);
    }

    public function test_get_today_action_returns_correct_data_after_add(): void
    {
        $this->limiter->recordAction('user-xyz', 'add', 55);

        $action = $this->limiter->getTodayAction('user-xyz');

        $this->assertSame('add', $action['action']);
        $this->assertSame(55, $action['game_id']);
    }

    // ── clearAction ────────────────────────────────────────────────────────────

    public function test_clear_action_removes_recorded_action(): void
    {
        $this->limiter->recordAction('user-abc', 'vote', 3);
        $this->assertTrue($this->limiter->hasActedToday('user-abc'));

        $this->limiter->clearAction('user-abc');

        $this->assertFalse($this->limiter->hasActedToday('user-abc'));
        $this->assertNull($this->limiter->getTodayAction('user-abc'));
    }

    public function test_clear_action_on_user_with_no_action_is_safe(): void
    {
        $this->limiter->clearAction('user-never-acted');

        $this->assertFalse($this->limiter->hasActedToday('user-never-acted'));
    }

    // ── allow_unlimited_actions bypass ────────────────────────────────────────

    public function test_has_acted_returns_false_when_unlimited_actions_enabled(): void
    {
        config(['services.app.allow_unlimited_actions' => true]);
        $this->limiter->recordAction('user-abc', 'vote', 1);

        $this->assertFalse($this->limiter->hasActedToday('user-abc'));
    }

    public function test_get_today_action_returns_null_when_unlimited_actions_enabled(): void
    {
        config(['services.app.allow_unlimited_actions' => true]);
        $this->limiter->recordAction('user-abc', 'vote', 1);

        $this->assertNull($this->limiter->getTodayAction('user-abc'));
    }

    // ── cache key isolation ────────────────────────────────────────────────────

    public function test_cache_key_includes_current_date(): void
    {
        $userId = 'user-date-test';
        $today = now()->format('Y-m-d');

        $this->limiter->recordAction($userId, 'vote', 1);

        $this->assertTrue(Cache::has("daily_action:{$userId}:{$today}"));
    }

    public function test_record_action_stores_correct_structure(): void
    {
        $userId = 'user-struct';
        $this->limiter->recordAction($userId, 'add', 100);

        $stored = Cache::get('daily_action:' . $userId . ':' . now()->format('Y-m-d'));

        $this->assertSame('add', $stored['action']);
        $this->assertSame(100, $stored['game_id']);
        $this->assertStringContainsString(now()->format('Y-m-d'), $stored['at']);
    }
}
