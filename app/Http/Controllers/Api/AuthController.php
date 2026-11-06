<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        unset($validated['password_confirmation']);

        $user = User::create($validated);
        [$token, $plainTextToken] = $this->createToken($user);

        return ApiResponse::success([
            'user' => $user,
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error(
                'invalid_credentials',
                'Email atau kata sandi tidak sesuai.',
                ['email' => ['Email atau kata sandi tidak sesuai.']],
                401,
            );
        }

        [$token, $plainTextToken] = $this->createToken($user);

        return ApiResponse::success([
            'user' => $user,
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')?->delete();

        return ApiResponse::success(['message' => 'Berhasil keluar.']);
    }

    private function createToken(User $user): array
    {
        $plainTextToken = Str::random(80);
        $token = $user->apiTokens()->create([
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addHours(12),
        ]);

        return [$token, $plainTextToken];
    }
}
