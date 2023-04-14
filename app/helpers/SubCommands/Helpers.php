<?php

use Barryvdh\LaravelIdeHelper\ClassMapGenerator;
use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Illuminate\Support\Facades\Artisan;

/**
 * Generate ide-helpers using the laravel-ide-helpers package.
 */

function handle()
{
    $targetDir = base_path('vendor/_ldt');

    if (class_exists(\Spatie\Analytics\Analytics::class)) {
        app()->bind(\Spatie\Analytics\Analytics::class, function () {
            return new class() {};
        });
    }

    app()->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);

    if (!file_exists($targetDir)) {
        mkdir($targetDir);
    }

    // @todo: Make dynamic.
    // When multiple directories are loaded, manually autoload them.
    $dirs = glob('twill/examples/**/app/Models', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        if (file_exists($dir)) {
            $classMap = ClassMapGenerator::createMap($dir);

            // Sort list so it's stable across different environments
            ksort($classMap);

            foreach ($classMap as $model => $path) {
                if (!class_exists($model)) {
                    require_once $path;
                }
            }
        }
    }

    Artisan::call('ide-helper:models',
        [
            '--nowrite' => true,
            '--filename' =>  $targetDir . DIRECTORY_SEPARATOR . 'models.php',
            '--dir' => [
                'app/Models',
                // @todo: Autoload the contents.
                'twill/examples/**/app/Models',
            ],
        ]
    );
    Artisan::call('ide-helper:generate',
        [
            'filename' =>  $targetDir . DIRECTORY_SEPARATOR . 'facades.php'
        ]
    );
}

handle();
