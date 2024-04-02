<?php

namespace App\Lsp\LspValidators;

use App\Lsp\DiagnosticError;
use Illuminate\Support\Collection;
use Phpactor\LanguageServerProtocol\TextDocumentItem;

use function Safe\preg_match_all;

class LivewireLspValidate extends BaseLspValidator
{
    public function getErrors(TextDocumentItem $document): array
    {
        $errors = [];
        if ($component = $this->store->findComponentForFile($document)) {
            $matches = [];
            preg_match_all(
                '/wire:(\w+){1}\.?(?:\w*)="([a-zA-Z0-9.\-_]*)"/',
                $document->text,
                $matches,
                PREG_OFFSET_CAPTURE
            );

            $workableArray = Collection::make();
            foreach ($matches[1] as $index => $type) {
                $workableArray->push([
                    'name' => $matches[2][$index][0],
                    'type' => $type[0],
                    'pos' => $matches[2][$index][1]
                ]);
            }

            $nestedProps = [];
            foreach ($component->wireProps as $prop => $className) {
                $nestedComponent = $this->store->findComponentForClass('\\' . $className);
                if (!$nestedComponent) {
                    continue;
                }
                foreach (array_keys($nestedComponent->wireProps) as $nestedProp) {
                    $nestedProps[] = $prop . '.' . $nestedProp;
                }
            }

            $wireModels = $workableArray
                ->filter(function ($item) {
                    // Only get models.
                    return $item['type'] === 'model';
                })
                ->filter(function ($item) use ($component) {
                    // Check if they are in the list.
                    return !array_key_exists($item['name'], $component->wireProps);
                })
                ->filter(function ($item) use ($nestedProps) {
                    // Check if they are nested.
                    return !in_array($item['name'], $nestedProps);
                });


            foreach ($wireModels as $missingWireable) {
                $errors[] = new DiagnosticError(
                    error: 'Wireable not found: ' . $missingWireable['name'],
                    type: DiagnosticError::TYPE_NOT_EXISTING,
                    startPos: $missingWireable['pos'],
                    endPos: $missingWireable['pos'] + strlen($missingWireable['name']),
                    provideAction: false,
                );
            }

            $wireMethods = $workableArray
                ->filter(function ($item) {
                    // Only get models.
                    return $item['type'] !== 'model';
                })
                ->filter(function ($item) use ($component) {
                    // Check if they are in the list.
                    return !array_key_exists($item['name'], $component->wireMethods);
                });

            foreach ($wireMethods as $missingWireable) {
                $errors[] = new DiagnosticError(
                    error: 'Method not found: ' . $missingWireable['name'],
                    type: DiagnosticError::TYPE_NOT_EXISTING,
                    startPos: $missingWireable['pos'],
                    endPos: $missingWireable['pos'] + strlen($missingWireable['name']),
                    provideAction: false,
                );
            }
        }

        return $errors;
    }
}
