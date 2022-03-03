<?php

$projectPath = $argv[1];
unset($argv[1]);

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Partial copy of artisan.
|--------------------------------------------------------------------------
| This is  hard copy of the artisan file. It will load the custom commands used to extract
| required data from the host application.
*/

require $projectPath . '/vendor/autoload.php';

$app = require_once $projectPath . '/bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

$options = [];

foreach (explode(' ', $argv[3] ?? '') as $option) {
    if (!empty($option)) {
        [$key, $value] = explode('=', $option);
        $options[$key] = $value;
    }
}

if ($argv[2] === 'views') {
    include_once __DIR__ . '/SubCommands/Views.php';
} elseif ($argv[2] === 'config') {
    include_once __DIR__ . '/SubCommands/Config.php';
} elseif ($argv[2] === 'snippets') {
    include_once __DIR__ . '/SubCommands/Snippets.php';
}
