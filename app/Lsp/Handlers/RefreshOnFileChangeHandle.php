<?php

namespace App\Lsp\Handlers;

use App\DataStore;
use App\Logger;
use Phpactor\LanguageServerProtocol\DidSaveTextDocumentParams;
use Phpactor\LanguageServerProtocol\VersionedTextDocumentIdentifier;
use Phpactor\LanguageServer\Core\Handler\Handler;

class RefreshOnFileChangeHandle implements Handler
{
    public function __construct(public DataStore $store)
    {
    }

    public function methods(): array
    {
        return [
            'textDocument/didSave' => 'refreshStore',
        ];
    }

    public function refreshStore(DidSaveTextDocumentParams $params): void
    {
        if ($this->isViewOrLivewireFile($params->textDocument)) {
            $this->store->refreshAvailableComponents(force: true);
        }
    }

    private function isViewOrLivewireFile(VersionedTextDocumentIdentifier $document): bool
    {
        return str_ends_with($document->uri, '.blade.php') ||
            str_contains($document->uri, 'app/Http/Livewire') ||
            str_contains($document->uri, 'app/View');
    }
}
