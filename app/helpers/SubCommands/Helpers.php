<?php

use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Illuminate\Support\Facades\Artisan;

/**
 * Generate ide-helpers using the laravel-ide-helpers package.
 */

function handle()
{
    $targetDir = base_path('vendor/_ldt');
    app()->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);


    if (!file_exists($targetDir)) {
        mkdir($targetDir);
    }

    Artisan::call('ide-helper:models',
        [
            'noWrite', true,
            'filename' =>  $targetDir . DIRECTORY_SEPARATOR . 'models.php'
        ]
    );
    Artisan::call('ide-helper:generate',
        [
            'filename' =>  $targetDir . DIRECTORY_SEPARATOR . 'facades.php'
        ]
    );
}

handle();
