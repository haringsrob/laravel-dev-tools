<?php

namespace App\Lsp;

use Amp\Promise;
use App\DataStore;
use App\Logger;
use App\Lsp\LspValidators\ComponentLspValidate;
use App\Lsp\LspValidators\LivewireComponentLspValidate;
use Phpactor\LanguageServer\Core\CodeAction\CodeActionProvider as CodeActionCodeActionProvider;
use Phpactor\LanguageServerProtocol\CodeAction;
use Phpactor\LanguageServerProtocol\Command;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\Range;

use function Amp\call;

class CodeActionProvider implements CodeActionCodeActionProvider
{
    public function __construct(public DataStore $store)
    {
    }

    public function provideActionsFor(TextDocumentItem $textDocument, Range $range): Promise
    {
        return call(function () use ($textDocument) {
            $bladeErrors = (new ComponentLspValidate($this->store))->getErrors($textDocument);

            $actions = [];

            foreach ($bladeErrors as $error) {
                if ($error->provideAction && $error->type === DiagnosticError::TYPE_NOT_EXISTING) {
                    $actions[] = new CodeAction(
                        title: 'Create component with class: ' . $error->componentName,
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

            $livewireErrors = (new LivewireComponentLspValidate($this->store))->getErrors($textDocument);

            Logger::logdbg($livewireErrors);

            foreach ($livewireErrors as $error) {
                if ($error->provideAction && $error->type === DiagnosticError::TYPE_NOT_EXISTING) {
                    $actions[] = new CodeAction(
                        title: 'Create livewire component: ' . $error->componentName,
                        kind: 'quickfix',
                        diagnostics: [$error->getDiagnostic($textDocument)],
                        command: new Command(
                            'Create livewire component',
                            'create_livewire_component',
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
