<?php

namespace App\Dto;

class BladeDirectiveData
{
    public function __construct(
        public string $name,
        public bool $hasEnd = false,
        public ?string $file = null,
        public ?string $class = null,
        public int $line = 0
    ) {
    }

    public function getHoverData(): string
    {
        return $this->class ?? $this->file ?? $this->name;
    }
}
