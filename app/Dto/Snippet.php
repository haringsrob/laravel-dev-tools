<?php

namespace App\Dto;

class Snippet
{
    public string $name;
    public string $prefix;
    public string $body;
    public string $description;

    public function __construct($name, $prefix, $body, $description = '')
    {
        $this->name = $name;
        $this->prefix = $prefix;
        $this->body = $body;
        $this->description = $description;
    }
}
