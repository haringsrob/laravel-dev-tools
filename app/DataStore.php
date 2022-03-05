<?php

namespace App;

use App\Dto\SnippetDto;
use App\Dto\BladeComponentData;
use App\Util\Path;
use Illuminate\Support\Collection;
use Phar;

class DataStore
{
    public Collection $availableComponents;

    public function __construct()
    {
        $this->availableComponents = collect();
    }

    public function refreshAvailableComponents(bool $force = false): Collection
    {
        if ($this->availableComponents->isEmpty() || $force) {
            $commandBase = PHP_BINARY . ' ' . Path::getBaseDir() . 'laravel-dev-generators';
            if ($phar = Phar::running(false)) {
                $commandBase = $phar;
            }
            $command = $commandBase . ' snippets --return ' . getcwd();

            $result = shell_exec($command);

            if (strpos($result, 'Exception')) {
                Logger::logdbg($result);
            }

            if ($result) {
                $this->availableComponents = $this->getComponentsFromData(json_decode($result, true));
            } else {
                $this->availableComponents = collect([]);
            }
        }
        return $this->availableComponents;
    }

    private function getComponentsFromData(array $data): Collection
    {
        $collection = collect([]);
        foreach ($data as $item) {
            if (in_array($item['type'], [SnippetDto::TYPE_COMPONENT, SnippetDto::TYPE_LIVEWIRE])) {
                $collection->add(new BladeComponentData(
                    name: $item['name'],
                    file: !empty($item['file']) ? $item['file'] : null,
                    class: !empty($item['class']) ? $item['class'] : null,
                    doc: !empty($item['doc']) ? $item['doc'] : null,
                    views: $item['views'] ?? [],
                    type: $item['type'],
                    arguments: $item['arguments'],
                    hasSlot: $item['hasSlot'] ?? false,
                ));
            }
        }

        return $collection;
    }
}
