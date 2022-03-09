<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

/**
 * @todo: currently not functional
 */
class GenerateViews extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'views {path}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Gets all views available';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $path = $this->argument('path', null);
        $runner = base_path('app/helpers/runner.php');
        $output = shell_exec("php $runner $path views");

        echo $output;
    }
}
