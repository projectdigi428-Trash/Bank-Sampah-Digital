<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\RequireRole;
use App\Http\Responses\ApiResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.token' => AuthenticateApiToken::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $exception, \Illuminate\Http\Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return ApiResponse::error(
                    'validation_error',
                    'Data yang dikirim tidak valid.',
                    $exception->errors(),
                    $exception->status,
                );
            }

            if ($exception instanceof AuthenticationException) {
                return ApiResponse::error('unauthenticated', 'Autentikasi diperlukan.', [], 401);
            }

            if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
                return ApiResponse::error('not_found', 'Data tidak ditemukan.', [], 404);
            }

            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
                $status = $exception->getStatusCode();

                return ApiResponse::error(
                    $status === 403 ? 'forbidden' : 'http_error',
                    $exception->getMessage() ?: 'Permintaan tidak dapat diproses.',
                    [],
                    $status,
                );
            }

            return ApiResponse::error('server_error', 'Terjadi kesalahan internal.', [], 500);
        });
    })->create();
