<?php

use App\Exceptions\InvalidCsvFileException;
use App\Exceptions\InvalidCsvHeaderException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function ($exceptions) {
    $exceptions->render(function (InvalidCsvFileException|InvalidCsvHeaderException $e, Request $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'file' => [$e->getMessage()],
                ],
            ], 422);
        }

        return null;
    });
})->create();
