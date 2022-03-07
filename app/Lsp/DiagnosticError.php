<?php

namespace App\Lsp;

class DiagnosticError
{
    public function __construct(
        public string $error,
        public int $startPos,
        public int $endPos,
    ) {
    }
}
