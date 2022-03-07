<?php

namespace App\Lsp\Handlers;

use Amp\Promise;
use App\DataStore;
use App\Dto\BladeComponentData;
use App\Lsp\Traits\GetsDocumentErrors;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsProvider;
use Phpactor\LanguageServerProtocol\TextDocumentItem;

use function Amp\call;

class BladeValidatorHandler implements DiagnosticsProvider
{
    use GetsDocumentErrors;

    public function __construct(public DataStore $store)
    {
    }

    public function provideDiagnostics(TextDocumentItem $textDocument): Promise
    {
        return call(function () use ($textDocument) {
            $componentsSimple = ['x-slot'];
            $this->store->availableComponents->each(
                function (BladeComponentData $bladeComponentData) use (&$componentsSimple) {
                    $componentsSimple[] = $bladeComponentData->name;
                    $componentsSimple[] = $bladeComponentData->altName;
                }
            );

            $errors = $this->getErrors($textDocument->text, $componentsSimple);

            $diagnostics = [];

            foreach ($errors as $error) {
                $diagnostics[] = $error->getDiagnostic($textDocument);
            }

            return $diagnostics;
        });
    }
}
