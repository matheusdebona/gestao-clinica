<?php

namespace App\Http\Middleware;

use App\Support\CurrentClinic;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentClinic
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            CurrentClinic::fromUser($user);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        CurrentClinic::forget();
    }
}
