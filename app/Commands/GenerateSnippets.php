<?php

namespace App\Commands;

use App\Logger;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Phar;

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
        $runner = base_path('app/helpers/runner.php');

        $baseDir = __DIR__ . '/../../';
        if ($pharBase = Phar::running()) {
            $baseDir = $pharBase;
        }

        $argv = [
            '',
            $path,
            'snippets',
        ];

        $script = file_get_contents($runner);
        $result = eval(str_replace('<?php', '', $script));

        // Here to make compiler happy!
        if ($result && $baseDir && $argv) {
        }

        return;
    }
}
