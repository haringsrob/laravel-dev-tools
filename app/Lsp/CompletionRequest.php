<?php

namespace App\Lsp;

use Phpactor\LanguageServerProtocol\Range;

class CompletionRequest
{
    public function __construct(
        public string $search,
        public ?Range $replaceRange = null
    ) {
    }
}
