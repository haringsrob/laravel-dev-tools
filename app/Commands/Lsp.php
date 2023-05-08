<?php

namespace App\Commands;

use App\Logger;
use App\BladeDispatcherFactory;
use LaravelZero\Framework\Commands\Command;
use Phpactor\LanguageServer\LanguageServerBuilder;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextProvider\CliContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Dumper\ServerDumper;
use Symfony\Component\VarDumper\VarDumper;

class Lsp extends Command
{
    protected $signature = 'lsp';
    protected $description = 'Run the lsp over stdio';

    public function handle(): void
    {
        $cloner = new VarCloner();
        $fallbackDumper = \in_array(\PHP_SAPI, ['cli', 'phpdbg']) ? new CliDumper() : new HtmlDumper();
        $dumper = new ServerDumper('tcp://127.0.0.1:9912', $fallbackDumper, [
            'cli' => new CliContextProvider(),
            'source' => new SourceContextProvider(),
        ]);

        VarDumper::setHandler(function ($var) use ($cloner, $dumper) {
            $dumper->dump($cloner->cloneVar($var));
        });


        $logger = Logger::getLogger();
        LanguageServerBuilder::create(new BladeDispatcherFactory($logger), $logger)
            ->build()
            ->run();
    }
}
