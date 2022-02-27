<?php

namespace App\Dto;

class Directive implements SnippetDto
{
    public string $name = '';
    public bool $hasEnd = false;

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
}
