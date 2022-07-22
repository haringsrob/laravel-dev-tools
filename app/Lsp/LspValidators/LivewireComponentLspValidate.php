<?php

namespace App\Lsp\LspValidators;

use App\Dto\BladeComponentData;
use App\Logger;
use App\Lsp\DiagnosticError;
use Phpactor\LanguageServerProtocol\TextDocumentItem;

class LivewireComponentLspValidate extends BaseLspValidator
{
    private $patternSelfClosing = "/
            <
                \s*
                livewire:([\w\-\:\.]*)
                \s*
                (?<attributes>
                    (?:
                        \s+
                        (?:
                            (?:
                                \{\{\s*\\\$attributes(?:[^}]+?)?\s*\}\}
                            )
                            |
                            (?:
                                [\w\-:.@]+
                                (
                                    =
                                    (?:
                                        \\\"[^\\\"]*\\\"
                                        |
                                        \'[^\']*\'
                                        |
                                        [^\'\\\"=<>]+
                                    )
                                )?
                            )
                        )
                    )*
                    \s*
                )
            \/>
        /x";


    private $patternOpeningTag = "/
            <
                \s*
                livewire:([\w\-\:\.]*)
                (?<attributes>
                    (?:
                        \s+
                        (?:
                            (?:
                                \{\{\s*\\\$attributes(?:[^}]+?)?\s*\}\}
                            )
                            |
                            (?:
                                [\w\-:.@]+
                                (
                                    =
                                    (?:
                                        \\\"[^\\\"]*\\\"
                                        |
                                        \'[^\']*\'
                                        |
                                        [^\'\\\"=<>]+
                                    )
                                )?
                            )
                        )
                    )*
                    \s*
                )
                (?<![\/=\-])
            >
        /x";

    private $patternClosingTag = "/<\/\s*livewire:[\w\-\:\.]*\s*>/";

    /**
     * @return DiagnosticError[]
     */
    public function getErrors(TextDocumentItem $document): array
    {
        $livewireComponents = $this->store->availableComponents->filter(function (BladeComponentData $component) {
            return $component->livewire;
        });

        $doc = $document->text;

        $selfClosing = [];
        $opening = [];
        $closing = [];

        preg_match_all($this->patternSelfClosing, $doc, $selfClosing, PREG_OFFSET_CAPTURE);
        preg_match_all($this->patternOpeningTag, $doc, $opening, PREG_OFFSET_CAPTURE);
        preg_match_all($this->patternClosingTag, $doc, $closing, PREG_OFFSET_CAPTURE);

        $componentNames = $livewireComponents->map(function (BladeComponentData $data) {
            return $data->name;
        })->toArray();

        $errors = [];

        foreach ($selfClosing[1] as $item) {
            if (!in_array('livewire:' . $item[0], $componentNames)) {
                $errors[] = new DiagnosticError(
                    error: 'Livewire Component not found: ' . $item[0],
                    type: DiagnosticError::TYPE_NOT_EXISTING,
                    componentName: $item[0],
                    startPos: $item[1],
                    endPos: $item[1] + strlen($item[0]),
                    isLivewire: true,
                    provideAction: true,
                );
            }
        }

        $closingTags = [];

        foreach ($closing[0] as $item) {
            $cleaned = rtrim(ltrim($item[0], '</x-'), '>');
            $closingTags[] = [$cleaned, $item[1]];
            continue;
            /* if (!in_array('x-' . $cleaned, $availableComponents)) { */
                // For now we do not provide this as it may be annoying?
                /* $errors[] = new DiagnosticError( */
                /*     error: 'Component not found: ' . $cleaned, */
                /*     type: DiagnosticError::TYPE_NOT_EXISTING, */
                /*     startPos: $item[1], */
                /*     endPos: $item[1] + strlen($item[0]) */
                /* ); */
            /* } */
        }

        foreach ($opening[1] as $item) {
            $isClosed = false;

            foreach ($closingTags as $key => $closingTag) {
                // Must be prefixed with livewire here.
                if ($closingTag[0] === 'livewire:' . $item[0]) {
                    $isClosed = true;
                    unset($closingTags[$key]);
                    break;
                }
            }

            if (!$isClosed) {
                $errors[] = new DiagnosticError(
                    error: 'Livewire component not closed: ' . $item[0],
                    type: DiagnosticError::TYPE_UNCLOSED,
                    startPos: $item[1],
                    endPos: $item[1] + strlen($item[0])
                );
            }

            if (!in_array('livewire:' . $item[0], $componentNames)) {
                $errors[] = new DiagnosticError(
                    error: 'Livewire component not found: ' . $item[0],
                    type: DiagnosticError::TYPE_NOT_EXISTING,
                    componentName: $item[0],
                    startPos: $item[1],
                    isLivewire: true,
                    endPos: $item[1] + strlen($item[0]),
                    provideAction: true
                );
            }
        }

        foreach ($closingTags as $closingTag) {
            $errors[] = new DiagnosticError(
                error: 'Livewire component opening not found: ' . $closingTag[0],
                type: DiagnosticError::TYPE_UNOPENED,
                startPos: $closingTag[1],
                endPos: $closingTag[1] + strlen($closingTag[0])
            );
        }

        return $errors;
    }
}
