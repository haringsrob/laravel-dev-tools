<?php

namespace App\Commands;

use App\Dto\Component;
use App\Dto\Directive;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;

use function base_path;
use function exec;
use function invade;

class GenerateSnippets extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'snippets {path}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Generates vscode and vim compatible snippets for all blade/livewire components and directives';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $path = $this->argument('path', null);
        $runner = base_path('app/helpers/runner.php');
        $output = shell_exec("php $runner $path snippets");

        $targetPath = $path . '/vendor/haringsrob/laravel-dev-generators/snippets/';

        if (!File::exists($targetPath)) {
            File::makeDirectory($targetPath, 0755, true);
        }

        file_put_contents($targetPath . 'blade.json', $output);
    }
}
