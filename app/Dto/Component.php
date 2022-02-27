<?php

namespace App\Dto;

class Component implements SnippetDto
{
    public array $arguments = [];
    public string $name = '';
    public string $file = '';
    public bool $livewire = false;

    public function toEntry(): Snippet
    {
        $body = "<{$this->name}";

        $i = 1;

        $skipArgs = ['componentName', 'attributes'];
        if ($this->livewire) {
            $skipArgs = [...$skipArgs, 'id', 'redirectTo'];
        }

        foreach ($this->arguments as $name => $argumentDetails) {
            if (in_array($name, $skipArgs)) {
                continue;
            }
            $body .= " {$name}=\"$$i\"";
            $i++;
        }

        if ($this->livewire) {
            $body .= "/>$0";
        } else {
            $body .= ">$0</{$this->name}>";
        }

        return new Snippet($this->name, "<{$this->name}", $body);
    }
}
