<?php

use App\Modules\Identity\Http\Middleware\AuthenticateJwt;
use App\Modules\Observability\Http\Controllers\MetricsController;
use App\Modules\Observability\Http\Middleware\ObserveHttpRequests;
use App\Modules\Shared\Http\ProblemDetailsRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/metrics', MetricsController::class)->name('metrics');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ObserveHttpRequests::class);
        $middleware->alias(['auth.jwt' => AuthenticateJwt::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => true);
        $exceptions->render(
            fn (Throwable $e, Request $request) => app(ProblemDetailsRenderer::class)->render($e, $request),
        );
    })->create();
