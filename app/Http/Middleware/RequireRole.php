<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            return ApiResponse::error('forbidden', 'Anda tidak memiliki izin untuk melakukan tindakan ini.', [], 403);
        }

        return $next($request);
    }
}
