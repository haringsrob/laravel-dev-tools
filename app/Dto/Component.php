<?php

namespace App\Dto;

use InvalidArgumentException;

class Component implements SnippetDto
{
    public array $arguments = [];
    public ?string $classDoc = null;
    public array $views = [];

    public function __construct(
        public string $name,
        public ?string $altName = null,
        public ?string $file = null,
        array $views = [],
        public ?string $class = null,
        public bool $livewire = false,
        public bool $simpleView = false
    ) {
        $this->arguments = $this->getPossibleAttributes();
        $this->classDoc = $this->getClassDoc();

        $this->views = $this->matchViewsWithPath($views);

        // If there is no file, we take that of the first view.
        if (null === $file && count($this->views) > 0) {
            $this->file = array_values($this->views)[0];
        }
    }

    public function matchViewsWithPath(array $views): array
    {
        $result = [];

        $viewFactory = invade(app('view'));

        foreach ($views as $viewName) {
            try {
                $result[$viewName] = realpath($viewFactory->finder->find($viewFactory->normalizeName($viewName)));
            } catch (InvalidArgumentException) {
            }
        }

        return $result;
    }

    public function hasSlot(): bool
    {
        if (str_ends_with($this->file, '.blade.php')) {
            return preg_match('/{{\s*\$slot\s*}}/', file_get_contents($this->file)) === 1;
        } elseif (isset(array_values($this->views)[0])) {
            return preg_match('/{{\s*\$slot\s*}}/', file_get_contents(array_values($this->views)[0])) === 1;
        }
        return false;
    }

    private function getClassDoc(): ?string
    {
        if (!$this->class) {
            return null;
        }

        $class = new \ReflectionClass($this->class);

        return $class->getDocComment();
    }

    private function getPossibleAttributes(): array
    {
        if (!$this->class) {
            return [];
        }

        $class = new \ReflectionClass($this->class);
        $result = [];
        $ignore = ['componentName', 'attributes'];
        /** @var \ReflectionProperty $attribute */
        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $attribute) {
            if (!in_array($attribute->getName(), $ignore)) {
                $result[$attribute->getName()] = [
                    'type' => $attribute->getType()?->getName(),
                    'default' => $attribute->getDefaultValue(),
                    'doc' => $attribute->getDocComment()
                ];
            }
        }

        return $result;
    }

    private function getType()
    {
        if ($this->livewire) {
            return self::TYPE_LIVEWIRE;
        }
        if ($this->simpleView) {
            return self::TYPE_VIEW;
        }

        return self::TYPE_COMPONENT;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'altName' => $this->altName,
            'file' => $this->file,
            'class' => $this->class,
            'doc' => $this->classDoc,
            'livewire' => $this->livewire,
            'arguments' => $this->arguments,
            'views' => $this->views,
            'hasSlot' => $this->hasSlot(),
            'type' => $this->getType()
        ];
    }
}
