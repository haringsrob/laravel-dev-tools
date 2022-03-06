<?php

namespace App;

use App\Dto\SnippetDto;
use App\Dto\BladeComponentData;
use App\Dto\BladeDirectiveData;
use App\Util\Path;
use Illuminate\Support\Collection;
use Phar;

class DataStore
{
    public Collection $availableComponents;
    public Collection $availableDirectives;

    public function __construct()
    {
        $this->availableComponents = collect();
        $this->availableDirectives = collect();
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

            if ($result) {
                try {
                    $decoded = json_decode($result, true, JSON_THROW_ON_ERROR);
                    if (!is_array($decoded)) {
                        return $this->availableComponents;
                    }
                    // @todo: Merge these as it is wasting computing power by looping twice.
                    $this->availableComponents = $this->getComponentsFromData($decoded);
                    $this->availableDirectives = $this->getDirectivesFromData($decoded);
                } catch (\Exception $e) {
                    Logger::logException($e);
                }
            }
        }
        return $this->availableComponents;
    }

    private function getDirectivesFromData(array $data): Collection
    {
        $collection = collect([]);
        foreach ($data as $item) {
            if (in_array($item['type'], [SnippetDto::TYPE_DIRECTIVE])) {
                $collection->add(new BladeDirectiveData(
                    name: $item['name'],
                    hasEnd: $item['hasEnd'],
                    file: $item['file'] ?? null,
                    class: $item['class'] ?? null,
                    line: $item['line'] ?? 0
                ));
            }
        }

        return $collection;
    }

    private function getComponentsFromData(array $data): Collection
    {
        $collection = collect([]);
        foreach ($data as $item) {
            if (in_array($item['type'], [SnippetDto::TYPE_COMPONENT, SnippetDto::TYPE_LIVEWIRE])) {
                $collection->add(new BladeComponentData(
                    name: $item['name'],
                    altName: $item['altName'],
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
