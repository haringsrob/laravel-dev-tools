<?php

namespace App\Lsp;

use Amp\Promise;
use App\DataStore;
use App\Dto\BladeComponentData;
use App\Lsp\Traits\GetsDocumentErrors;
use Phpactor\LanguageServer\Core\CodeAction\CodeActionProvider as CodeActionCodeActionProvider;
use Phpactor\LanguageServerProtocol\CodeAction;
use Phpactor\LanguageServerProtocol\Command;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\Range;

use function Amp\call;

class CodeActionProvider implements CodeActionCodeActionProvider
{
    use GetsDocumentErrors;

    public function __construct(public DataStore $store)
    {
    }

    public function provideActionsFor(TextDocumentItem $textDocument, Range $range): Promise
    {
        return call(function () use ($textDocument) {
            $componentsSimple = ['x-slot'];
            $this->store->availableComponents->each(function (BladeComponentData $bladeComponentData) use (&$componentsSimple) {
                $componentsSimple[] = $bladeComponentData->name;
                $componentsSimple[] = $bladeComponentData->altName;
            });

            $errors = $this->getErrors($textDocument->text, $componentsSimple);

            $actions = [];

            foreach ($errors as $error) {
                if ($error->provideAction && $error->type === DiagnosticError::TYPE_NOT_EXISTING) {
                    $actions[] = new CodeAction(
                        title: 'Create component with class',
                        kind: 'quickfix',
                        diagnostics: [$error->getDiagnostic($textDocument)],
                        command: new Command(
                            'Create component with class',
                            'create_component',
                            [$error->componentName, $textDocument]
                        )
                    );
                }
            }

            return $actions;
        });
    }

    public function kinds(): array
    {
        return ['quickfix'];
    }
}
