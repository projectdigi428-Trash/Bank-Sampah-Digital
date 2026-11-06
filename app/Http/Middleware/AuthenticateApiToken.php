<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return ApiResponse::error('unauthenticated', 'Token autentikasi diperlukan.', [], 401);
        }

        $token = ApiToken::with('user')
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->first();

        if (! $token || $token->expires_at->isPast()) {
            return ApiResponse::error('unauthenticated', 'Token tidak valid atau sudah kedaluwarsa.', [], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('api_token', $token);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
