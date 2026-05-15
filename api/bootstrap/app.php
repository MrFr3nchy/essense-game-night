<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust proxies so X-Forwarded-For / X-Forwarded-Proto work behind load
        // balancers and reverse proxies. Set TRUSTED_PROXIES=* to trust all.
        $trustedProxies = env('TRUSTED_PROXIES', '');
        if ($trustedProxies !== '') {
            $middleware->trustProxies(at: $trustedProxies);
        }

        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
