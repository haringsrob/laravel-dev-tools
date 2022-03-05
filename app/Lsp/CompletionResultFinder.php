<?php

namespace App\Lsp;

use App\DataStore;
use App\Dto\SnippetDto;
use App\Lsp\CompletionRequest;
use Phpactor\LanguageServerProtocol\CompletionItem;
use Phpactor\LanguageServerProtocol\CompletionItemKind;
use Phpactor\LanguageServerProtocol\InsertTextFormat;
use Phpactor\LanguageServerProtocol\TextEdit;

class CompletionResultFinder
{
    public DataStore $dataStore;

    public function __construct(DataStore $dataStore)
    {
        $this->dataStore = $dataStore;
    }

    /**
     * @return CompletionItem[]
     */
    public function getArguments(CompletionRequest $completionRequest): array
    {
        /** @var BladeComponentData $component */
        $component = $this->dataStore->availableComponents->firstWhere('name', $completionRequest->element->name);

        $completionItems = [];

        // Find a matching component.
        if ($component) {
            $usedArguments = $completionRequest->element->getUsedArguments();
            foreach ($component->arguments as $name => $argumentData) {
                // Logic
                if (!in_array($name, $usedArguments) && strpos($name, ltrim($completionRequest->search, ':')) !== false) {
                    if (!str_starts_with($completionRequest->search, ':')) {
                        if ($argumentData['type'] !== 'bool') {
                            $completionItems[] = new CompletionItem(
                                label: $name . '=""',
                                detail: $argumentData['type'] ?? '',
                                documentation: $argumentData['doc'] ?? '',
                                kind: CompletionItemKind::TYPE_PARAMETER,
                                insertText: $name . '="$0"',
                                insertTextFormat: InsertTextFormat::SNIPPET
                            );
                        } else {
                            // Booleans can be true if no args.
                            $completionItems[] = new CompletionItem(
                                label: $name,
                                detail: $argumentData['type'] ?? '',
                                documentation: $argumentData['doc'] ?? '',
                                kind: CompletionItemKind::TYPE_PARAMETER,
                                insertText: $name,
                                insertTextFormat: InsertTextFormat::SNIPPET
                            );
                        }
                    }

                    $completionItems[] = new CompletionItem(
                        label: ':' . $name . '=""',
                        detail: $argumentData['type'] ?? '',
                        documentation: $argumentData['doc'] ?? '',
                        commitCharacters: [':', '-'],
                        kind: CompletionItemKind::TYPE_PARAMETER,
                        insertText: ($completionRequest->triggerChar === ':' ? '' : ':') . $name . '="$0"',
                        insertTextFormat: InsertTextFormat::SNIPPET
                    );
                }
            }
        }

        return $completionItems;
    }

    /**
     * @return CompletionItem[]
     */
    public function getComponents(CompletionRequest $completionRequest): array
    {
        if (empty($completionRequest->search)) {
            // Get out asap.
            return [];
        }

        $components = $this->dataStore->availableComponents->whereIn(
            'type',
            [SnippetDto::TYPE_COMPONENT, SnippetDto::TYPE_LIVEWIRE]
        );

        $completionItems = [];

        /** @var BladeComponentData $data */
        foreach ($components as $data) {
            // Check if our search matches.
            if (strpos($data->name, $completionRequest->search) === false) {
                continue;
            }

            // Build the snippet.
            $snippet = "<{$data->name} $0/>";
            if ($data->hasSlot) {
                $snippet = "<{$data->name}>$0</{$data->name}>";
            }

            $completionItems[] = new CompletionItem(
                label: "<{$data->name} />",
                documentation: $data->getHoverData(),
                detail: $data->getFile(),
                kind: CompletionItemKind::MODULE,
                textEdit: new TextEdit($completionRequest->replaceRange, $snippet),
                insertTextFormat: InsertTextFormat::SNIPPET
            );
        }

        return $completionItems;
    }
}
