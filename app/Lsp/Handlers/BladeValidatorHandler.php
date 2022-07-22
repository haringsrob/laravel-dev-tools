<?php

namespace App\Lsp\Handlers;

use Amp\Promise;
use App\DataStore;
use App\Lsp\LspValidators\ComponentLspValidate;
use App\Lsp\LspValidators\LivewireLspValidate;
use Illuminate\Support\Collection;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsProvider;
use Phpactor\LanguageServerProtocol\TextDocumentItem;

use function Amp\call;

class BladeValidatorHandler implements DiagnosticsProvider
{
    public array $validators;

    public function __construct(public DataStore $store)
    {
        $this->validators[] = new LivewireLspValidate($store);
        $this->validators[] = new ComponentLspValidate($store);
    }

    public function provideDiagnostics(TextDocumentItem $textDocument): Promise
    {
        return call(function () use ($textDocument) {
            $errors = Collection::make();

            foreach ($this->validators as $validator) {
                $errors = $errors->concat($validator->getErrors($textDocument));
            }

            $diagnostics = [];

            foreach ($errors as $error) {
                $diagnostics[] = $error->getDiagnostic($textDocument);
            }

            return $diagnostics;
        });
    }
}
