<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\ApplicationBuilder;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Application $app) {
        // Add your middleware here.
    })
    ->withExceptions(function (Application $app) {
        // Add your exceptions here.
    })
    ->create();
