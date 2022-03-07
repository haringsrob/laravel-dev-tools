<?php

namespace App\Lsp\Handlers;

use Amp\Promise;
use Amp\Success;
use App\DataStore;
use App\Dto\BladeComponentData;
use App\Logger;
use App\Lsp\DiagnosticError;
use App\Util\PositionConverter;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsProvider;
use Phpactor\LanguageServerProtocol\Diagnostic;
use Phpactor\LanguageServerProtocol\DiagnosticSeverity;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;

use function Amp\call;

class BladeValidatorHandler implements DiagnosticsProvider
{
    private $patternSelfClosing = "/
            <
                \s*
                x[-\:]([\w\-\:\.]*)
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
                x[-\:]([\w\-\:\.]*)
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

    private $patternClosingTag = "/<\/\s*x[-\:][\w\-\:\.]*\s*>/";

    public function __construct(public DataStore $store)
    {
    }

    /**
     * @return DiagnosticError[]
     */
    private function getErrors(string $doc, array $availableComponents): array
    {
        $selfClosing = [];
        $opening = [];
        $closing = [];

        preg_match_all($this->patternSelfClosing, $doc, $selfClosing, PREG_OFFSET_CAPTURE);
        preg_match_all($this->patternOpeningTag, $doc, $opening, PREG_OFFSET_CAPTURE);
        preg_match_all($this->patternClosingTag, $doc, $closing, PREG_OFFSET_CAPTURE);

        $errors = [];
        foreach ($selfClosing[1] as $item) {
            if (!in_array('x-' . $item[0], $availableComponents)) {
                $errors[] = new DiagnosticError(
                    error: 'Component not found: ' . $item[0],
                    startPos: $item[1],
                    endPos: $item[1] + strlen($item[0])
                );
            }
        }

        $closingTags = [];

        foreach ($closing[0] as $item) {
            $cleaned = rtrim(ltrim($item[0], '</x-'), '>');
            Logger::logdbg($cleaned);
            $closingTags[] = [$cleaned, $item[1]];
            if (!in_array('x-' . $cleaned, $availableComponents)) {
                $errors[] = new DiagnosticError(
                    error: 'Component not found: ' . $cleaned,
                    startPos: $item[1],
                    endPos: $item[1] + strlen($item[0])
                );
            }
        }

        foreach ($opening[1] as $item) {
            $isClosed = false;

            foreach ($closingTags as $key => $closingTag) {
                if ($closingTag[0] === $item[0]) {
                    $isClosed = true;
                    unset($closingTags[$key]);
                    break;
                }
            }

            if (!$isClosed) {
                $errors[] = new DiagnosticError(
                    error: 'Component not closed: ' . $item[0],
                    startPos: $item[1],
                    endPos: $item[1] + strlen($item[0])
                );
            }

            if (!in_array('x-' . $item[0], $availableComponents)) {
                $errors[] = new DiagnosticError(
                    error: 'Component not found: ' . $item[0],
                    startPos: $item[1],
                    endPos: $item[1] + strlen($item[0])
                );
            }
        }

        foreach ($closingTags as $closingTag) {
            $errors[] = new DiagnosticError(
                error: 'Component opening not found: ' . $closingTag[0],
                startPos: $closingTag[1],
                endPos: $closingTag[1] + strlen($closingTag[0])
            );
        }

        return $errors;
    }

    public function provideDiagnostics(TextDocumentItem $textDocument): Promise
    {
        Logger::logdbg('provideDiagnostics');
        return call(function () use ($textDocument) {
            $componentsSimple = ['x-slot'];
            $this->store->availableComponents->each(function (BladeComponentData $bladeComponentData) use (&$componentsSimple) {
                $componentsSimple[] = $bladeComponentData->name;
                $componentsSimple[] = $bladeComponentData->altName;
            });

            $errors = $this->getErrors($textDocument->text, $componentsSimple);

            $diagnostics = [];

            foreach ($errors as $error) {
                $diagnostics[] = new Diagnostic(
                    new Range(
                        PositionConverter::intByteOffsetToPosition($error->startPos, $textDocument->text),
                        PositionConverter::intByteOffsetToPosition($error->endPos, $textDocument->text),
                    ),
                    $error->error,
                    DiagnosticSeverity::ERROR
                );
            }

            return $diagnostics;
        });
    }
}
