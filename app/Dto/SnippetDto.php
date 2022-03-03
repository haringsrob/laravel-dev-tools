<?php

namespace App\Dto;

interface SnippetDto
{
    public const TYPE_DIRECTIVE = 'directive';
    public const TYPE_COMPONENT = 'component';
    public const TYPE_LIVEWIRE = 'livewire';
    public const TYPE_VIEW = 'view';

    public function toEntry(): Snippet;
    public function toArray(): array;
}
