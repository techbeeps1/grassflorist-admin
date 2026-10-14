<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->append(\App\Http\Middleware\DisallowIndexingMiddleware::class);
        $middleware->append(\App\Http\Middleware\SetApiLocale::class);
        $middleware->alias([
            'customer.active' => \App\Http\Middleware\EnsureCustomerIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->render(function (AuthenticationException $e, Request $request) {

            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            return null;
        });

    })
    ->create();