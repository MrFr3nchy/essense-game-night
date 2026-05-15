<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdentifyUser
{
    /**
     * Assign a persistent user ID via cookie. This is a lightweight
     * identification strategy — no auth required.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->cookie('voter_id');

        if (empty($userId)) {
            $userId = Str::uuid()->toString();
        }

        $request->merge(['voter_id' => $userId]);

        /** @var Response $response */
        $response = $next($request);

        // 1 year cookie
        $response->headers->setCookie(cookie(
            name: 'voter_id',
            value: $userId,
            minutes: 60 * 24 * 365,
            path: '/',
            secure: app()->environment('production'),
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $response;
    }
}
