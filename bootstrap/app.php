<?php

use App\Http\Middleware\EnsurePlanModule;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureWeddingAccess;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'wedding.access' => EnsureWeddingAccess::class,
            'plan.module' => EnsurePlanModule::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Route-model binding (including the scoped nested bindings in
        // routes/api.php) throws ModelNotFoundException, which Laravel turns
        // into a 404 carrying the ORM's own message — "No query results for
        // model [App\Models\Guest] 42" — leaking the model class and echoing
        // the probed id back, and surfacing that string in the UI. Map it to a
        // bare 404 instead: the same empty-message response `abort(404)`
        // produces, so "belongs to another wedding" and "does not exist" stay
        // indistinguishable to the caller.
        $exceptions->map(
            fn (ModelNotFoundException $e) => new NotFoundHttpException(previous: $e),
        );
    })->create();
