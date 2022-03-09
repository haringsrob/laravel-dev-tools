<?php

namespace App\Lsp\Commands;

use Amp\Promise;
use Amp\Success;
use App\DataStore;
use Illuminate\Support\Str;
use Phpactor\LanguageServer\Core\Command\Command;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsEngine;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServerProtocol\TextDocumentItem;

class CreateComponentCommand implements Command
{
    public function __construct(
        private ClientApi $api,
        private DataStore $dataStore,
        public DiagnosticsEngine $diagnosticsEngine
    ) {
    }

    public function __invoke(string $name, array $textDocument): Promise
    {
        $name = Str::Studly($name);
        $result = $this->dataStore->executeCommandAndRefresh("make:component $name");
        if (empty($result)) {
            $result = 'Successfully created new component';
        }
        $this->diagnosticsEngine->enqueue(TextDocumentItem::fromArray($textDocument));
        $this->api->window()->showMessage()->info(sprintf('%s', $result));
        return new Success();
    }
}
