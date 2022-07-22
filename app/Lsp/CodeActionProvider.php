<?php

namespace App\Lsp;

use Amp\Promise;
use App\DataStore;
use App\Dto\BladeComponentData;
use App\Lsp\LspValidators\ComponentLspValidate;
use Phpactor\LanguageServer\Core\CodeAction\CodeActionProvider as CodeActionCodeActionProvider;
use Phpactor\LanguageServerProtocol\CodeAction;
use Phpactor\LanguageServerProtocol\Command;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\Range;

use function Amp\call;

class CodeActionProvider implements CodeActionCodeActionProvider
{
    public ComponentLspValidate $validator;

    public function __construct(public DataStore $store)
    {
        $this->validator = new ComponentLspValidate($store);
    }

    public function provideActionsFor(TextDocumentItem $textDocument, Range $range): Promise
    {
        return call(function () use ($textDocument) {
            $errors = $this->validator->getErrors($textDocument);

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
