<?php

namespace App\Dto;

use App\DataStore;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Symfony\Component\VarDumper\VarDumper;

class Element
{
    private ?BladeComponentData $component = null;

    public function __construct(
        public string $name,
        public string $full,
        public int $startsAt,
        public int $endsAt,
        public DataStore $store
    ) {
    }

    public function getUsedArguments(): array
    {
        $tagCompiler = new ComponentTagCompiler();
        return array_keys(invade($tagCompiler)->getAttributesFromAttributeString($this->full));
    }

    public function getComponent(): ?BladeComponentData
    {
        if (!$this->component) {
            $components = $this->store->availableComponents->whereIn(
                'type',
                [
                    SnippetDto::TYPE_COMPONENT,
                    SnippetDto::TYPE_LIVEWIRE,
                    SnippetDto::TYPE_VIEW
                ]
            );
            $this->component = $components->firstWhere('name', $this->name) ?? $components->firstWhere('altName', $this->name);
        }

        return $this->component;
    }
}
