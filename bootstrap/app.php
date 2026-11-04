<?php

use App\Http\Controllers\Site\NotFoundController;
use App\Http\Middleware\CachePublicPages;
use App\Http\Middleware\SetSiteLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('site')->group(base_path('routes/site.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Public pages: no session, no cookies, whole-page cache.
        $middleware->group('site', [
            SetSiteLocale::class,
            CachePublicPages::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Missing posts and pages: follow a saved redirect, or show the site's own 404 page.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('admin', 'admin/*', 'livewire*', 'filament/*')) {
                return app(NotFoundController::class)($request);
            }
        });
    })->create();
