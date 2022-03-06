<?php

namespace App\Dto;

class BladeComponentData
{
    public function __construct(
        public string $name,
        public ?string $altName = null,
        public ?string $file,
        public ?string $class,
        public ?string $doc,
        public array $views,
        public string $type,
        public bool $livewire = false,
        public array $arguments = [],
        public bool $hasSlot = false,
    ) {
    }

    public function getHoverData(): string
    {
        return $this->doc ?? $this->getFile() ?? $this->class ?? '';
    }

    public function getFile(): ?string
    {
        if (!$this->file) {
            return null;
        }
        return str_replace(getcwd(), '', $this->file);
    }
}
