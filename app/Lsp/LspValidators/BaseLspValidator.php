<?php

namespace App\Lsp\LspValidators;

use Phpactor\LanguageServerProtocol\TextDocumentItem;
use App\DataStore;

abstract class BaseLspValidator
{
    public function __construct(
        public DataStore $store,
    ) {
    }

    /**
     * @return \App\Lsp\DiagnosticError[]
     */
    abstract public function getErrors(TextDocumentItem $document): array;
}
