<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('boxoffice', 'boxoffice/*') => route('boxoffice.login'),
            $request->is('admin', 'admin/*') => route('admin.login'),
            default => route('login'),
        });

        $middleware->redirectUsersTo(fn (Request $request) => match (true) {
            $request->is('boxoffice', 'boxoffice/*') => route('boxoffice.dashboard'),
            $request->is('admin', 'admin/*') => route('admin.home'),
            default => config('fortify.home', '/dashboard'),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('webhooks/*')
                || ($request->is('boxoffice/*', 'admin/*', 'compra/*') && $request->expectsJson() && ! $request->header('X-Inertia')),
        );
    })->create();
