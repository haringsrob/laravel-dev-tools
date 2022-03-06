<?php

namespace App\Dto;

class Directive implements SnippetDto
{
    public string $name = '';
    public bool $hasEnd = false;
    public ?string $file = null;
    public ?string $class = null;
    public int $line = 0;

    public function toEntry(): Snippet
    {
        $body = "@{$this->name}";

        if ($this->hasEnd) {
            $body .= "()\n$0\n@end{$this->name}";
        } else {
            $body .= "($0)";
        }

        return new Snippet($this->name, "@{$this->name}", $body);
    }

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
