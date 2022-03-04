<?php

namespace App\Commands;

use App\Logger;
use App\BladeDispatcherFactory;
use LaravelZero\Framework\Commands\Command;
use Phpactor\LanguageServer\LanguageServerBuilder;

class Lsp extends Command
{
    protected $signature = 'lsp';
    protected $description = 'Run the lsp over stdio';

    public function handle(): void
    {
        $logger = Logger::getLogger();
        Logger::logdbg('test');
        LanguageServerBuilder::create(new BladeDispatcherFactory($logger), $logger)
            ->build()
            ->run();
    }
}
