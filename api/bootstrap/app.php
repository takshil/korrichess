<?php

use App\Domain\Shared\DomainError;
use App\Http\Exceptions\DomainErrorRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            \App\Http\Middleware\DrainQueueAfterResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Refus métier levés par le domaine → réponse JSON { "error": "<code>" }.
        $exceptions->render((new DomainErrorRenderer())(...));
        $exceptions->dontReport(DomainError::class);
    })->create();
