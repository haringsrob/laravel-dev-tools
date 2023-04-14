<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Phar;

class GenerateRoutes extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'routes {path}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Gets routes as json';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $path = $this->argument('path');
        $runner = base_path('app/helpers/runner.php');

        $baseDir = __DIR__ . '/../../';
        if ($pharBase = Phar::running()) {
            $baseDir = $pharBase;
        }

        $argv = [
            '',
            $path,
            'routes',
        ];

        $script = file_get_contents($runner);
        $result = eval(str_replace('<?php', '', $script));

        // Here to make compiler happy!
        if ($result && $baseDir && $argv) {
        }

        return;
    }
}
