<?php

namespace App\Lsp;

use App\DataStore;
use App\Dto\BladeComponentData;
use App\Dto\Element;
use Phpactor\LanguageServerProtocol\Range;

class CompletionRequest
{
    public function __construct(
        public string $search,
        public string $type,
        public ?Element $element = null,
        public ?string $elementName = null,
        public ?Range $replaceRange = null,
        public ?string $triggerChar = null,
        public ?DataStore $store = null,
        public ?BladeComponentData $component = null,
    ) {
    }
}
