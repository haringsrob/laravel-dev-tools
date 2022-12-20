<?php

// @todo: This can be refactored out as it is using eval.
$projectPath = $argv[1];
unset($argv[1]);

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

if ($argv[2] === 'command') {
    include_once $baseDir . '/app/helpers/SubCommands/ExecuteArtisan.php';
} elseif ($argv[2] === 'views') {
    include_once $baseDir . '/app/helpers/SubCommands/Views.php';
} elseif ($argv[2] === 'config') {
    include_once $baseDir . '/app/helpers/SubCommands/Config.php';
} elseif ($argv[2] === 'snippets') {
    include_once $baseDir . '/app/helpers/SubCommands/Snippets.php';
} elseif ($argv[2] === 'container') {
    include_once $baseDir . '/app/helpers/SubCommands/Container.php';
} elseif ($argv[2] === 'helpers') {
    include_once $baseDir . '/app/helpers/SubCommands/Helpers.php';
}
