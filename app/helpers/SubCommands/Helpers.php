<?php

use Illuminate\Support\Facades\Artisan;

/**
 * Generate ide-helpers using the laravel-ide-helpers package.
 */

function handle()
{
    $targetDir = base_path('vendor/_ldt');

    if (class_exists(\Spatie\Analytics\Analytics::class)) {
        app()->bind(\Spatie\Analytics\Analytics::class, function () {
            return new class () {
            };
        });
    }

    app()->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);

    if (!file_exists($targetDir)) {
        mkdir($targetDir);
    }

    Artisan::call(
        'ide-helper:generate',
        [
            'filename' =>  $targetDir . DIRECTORY_SEPARATOR . 'facades.php',
            '--helpers' => true,
        ]
    );
    Artisan::call(
        'ide-helper:eloquent'
    );
}

handle();
