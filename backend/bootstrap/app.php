<?php

use App\Exceptions\UnresolvedCutoffTieException;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::error(
                    $e->getMessage() ?: 'Validation failed.',
                    $e->errors(),
                    422
                );
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error('Unauthenticated.', null, 401);
            }

            if ($e instanceof AuthorizationException) {
                return ApiResponse::error('Forbidden.', null, 403);
            }

            if ($e instanceof ModelNotFoundException) {
                return ApiResponse::error('Resource not found.', null, 404);
            }

            if ($e instanceof UnresolvedCutoffTieException) {
                return ApiResponse::error(
                    $e->getMessage(),
                    [
                        'cutoff_tie' => [
                            'round_id' => $e->roundId,
                            'advancement_count' => $e->advancementCount,
                            'tied_contestant_ids' => $e->tiedContestantIds,
                        ],
                    ],
                    409
                );
            }

            if ($e instanceof HttpExceptionInterface) {
                return ApiResponse::error(
                    $e->getMessage() ?: 'Request error.',
                    null,
                    $e->getStatusCode()
                );
            }

            return ApiResponse::error('An unexpected server error occurred.', null, 500);
        });
    })->create();
