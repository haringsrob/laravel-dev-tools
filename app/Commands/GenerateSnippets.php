<?php

namespace App\Commands;

use App\Logger;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;

use function base_path;

class GenerateSnippets extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'snippets {path} {--vscode} {--return}';

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
        $path = $this->argument('path');
        $vscode = $this->option('vscode') ?? false;
        $return = $this->option('return') ?? false;
        $runner = base_path('app/helpers/runner.php');
        $options = $return ? '' : 'snippets=true';
        $output = shell_exec("php $runner $path snippets $options");

        if ($vscode) {
            $targetPath = $path . '/.vscode/';

            if (!File::exists($targetPath)) {
                File::makeDirectory($targetPath, 0755, true);
            }

            file_put_contents($targetPath . '/blade.code-snippets', $output);

            $this->line('.vscode/blade.code-snippets created.');
        } elseif ($return) {
            echo $output;
        } else {
            $targetPath = $path . '/vendor/haringsrob/laravel-dev-generators/snippets/';

            if (!File::exists($targetPath)) {
                File::makeDirectory($targetPath, 0755, true);
            }
            file_put_contents($targetPath . '/blade.json', $output);
        }
    }
}
