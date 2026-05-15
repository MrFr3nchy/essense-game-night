<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Override any .env flag that would disable the daily action limit during tests.
        config(['services.app.allow_unlimited_actions' => false]);
    }
}
