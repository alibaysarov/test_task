<?php

use App\Exceptions\CurrentMasterNotFoundException;
use App\Exceptions\InvalidReferralCodeException;
use App\Http\Middleware\ResolveCurrentMaster;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Авторизация в тестовом проекте заглушена:
        // текущий мастер берётся из заголовка X-Master-Id.
        $middleware->api(prepend: [
            ResolveCurrentMaster::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([
            CurrentMasterNotFoundException::class,
            InvalidReferralCodeException::class,
        ]);

        $exceptions->render(function (CurrentMasterNotFoundException $exception): JsonResponse {
            return response()->json(['message' => $exception->getMessage()], 401);
        });

        $exceptions->render(function (InvalidReferralCodeException $exception): JsonResponse {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['code' => [$exception->getMessage()]],
            ], 422);
        });

        $exceptions->shouldRenderJsonWhen(fn (Request $request, \Throwable $exception): bool =>
            $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ValidationException $exception, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $exception->errors(),
                ], $exception->status);
            }

            return null;
        });
    })->create();
