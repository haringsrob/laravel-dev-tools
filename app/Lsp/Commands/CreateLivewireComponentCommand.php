<?php

namespace App\Lsp\Commands;

use Amp\Promise;
use Amp\Success;
use App\DataStore;
use App\Logger;
use Illuminate\Support\Str;
use Phpactor\LanguageServer\Core\Command\Command;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsEngine;
use Phpactor\LanguageServer\Core\Server\ClientApi;

class CreateLivewireComponentCommand implements Command
{
    public function __construct(
        public DataStore $dataStore,
        public DiagnosticsEngine $diagnosticsEngine,
        public ClientApi $api
    ) {
    }

    public function __invoke(string $name, array $textDocument): Promise
    {
        Logger::logdbg('Creating livewire command: ' . $name);
        $name = Str::studly($name);
        $result = $this->dataStore->executeCommandAndRefresh("make:livewire $name");
        if (empty($result)) {
            $result = 'Successfully created new component';
        }
        $this->diagnosticsEngine->enqueue(TextDocumentItem::fromArray($textDocument));
        $this->api->window()->showMessage()->info(sprintf('%s', $result));
        return new Success();
    }
}
