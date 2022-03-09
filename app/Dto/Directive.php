<?php

namespace App\Dto;

class Directive implements SnippetDto
{
    public string $name = '';
    public bool $hasEnd = false;
    public ?string $file = null;
    public ?string $class = null;
    public int $line = 0;

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'hasEnd' => $this->hasEnd,
            'type' => self::TYPE_DIRECTIVE,
            'file' => $this->file,
            'class' => $this->class,
            'line' => $this->line
        ];
    }
}
