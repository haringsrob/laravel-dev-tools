<?php

namespace App\Lsp;

use App\Util\PositionConverter;
use Phpactor\LanguageServerProtocol\Diagnostic;
use Phpactor\LanguageServerProtocol\DiagnosticSeverity;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;

class DiagnosticError
{
    public const TYPE_UNCLOSED = 'unclosed';
    public const TYPE_UNOPENED = 'unopened';
    public const TYPE_NOT_EXISTING = 'not_existing';

    public function __construct(
        public string $error,
        public string $type,
        public int $startPos,
        public int $endPos,
        public bool $provideAction = false,
        public bool $isLivewire = false,
        public ?string $componentName = null,
    ) {
    }

    public function getDiagnostic(TextDocumentItem $textDocument): Diagnostic
    {
        return new Diagnostic(
            new Range(
                PositionConverter::intByteOffsetToPosition($this->startPos, $textDocument->text),
                PositionConverter::intByteOffsetToPosition($this->endPos, $textDocument->text),
            ),
            $this->error,
            DiagnosticSeverity::ERROR
        );
    }
}
