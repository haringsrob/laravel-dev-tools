<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

/**
 * @todo: currently not functional
 */
class GenerateConfig extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'config {path}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Gets the dot annotated config options';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $path = $this->argument('path', null);
        $runner = base_path('app/helpers/runner.php');
        $output = shell_exec("php $runner $path config");

        echo $output;
    }
}
