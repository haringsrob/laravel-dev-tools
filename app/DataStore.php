<?php

namespace App;

use App\Dto\SnippetDto;
use App\Dto\BladeComponentData;
use App\Dto\BladeDirectiveData;
use App\Util\Path;
use Illuminate\Support\Collection;
use Phar;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Symfony\Component\VarDumper\VarDumper;

class DataStore
{
    public Collection $availableComponents;
    public Collection $availableDirectives;
    public static $inprogress = false;

    public function __construct()
    {
        $this->availableComponents = collect();
        $this->availableDirectives = collect();
    }

    public function executeCommandAndRefresh(string $command): string
    {
        return $this->executeCommand($command, true);
    }

    public function executeCommand(string $commandString, bool $refresh = false): string
    {
        $command = $this->getRunner() . ' run-command ' . getcwd() . " \"$commandString\"";

        $result = shell_exec($command);

        if ($refresh) {
            $this->refreshAvailableComponents(true);
        }

        return $result ?? '';
    }

    private function getRunner(): string
    {
        $commandBase = PHP_BINARY . ' ' . Path::getBaseDir() . 'laravel-dev-tools';

        if ($phar = Phar::running(false)) {
            return $phar;
        }

        return $commandBase;
    }

    /**
     * The current file, only supports views files (for now).
     */
    public function findComponentForFile(TextDocumentItem $file): ?BladeComponentData
    {
        $file = str_replace('file://', '', $file->uri);

        $matchingComponent = null;

        foreach ($this->availableComponents as $component) {
            if ($component->matchesView($file)) {
                $matchingComponent = $component;
                break;
            }
        }
        return $matchingComponent;
    }

    public function findComponentForClass(string $className): ?BladeComponentData
    {
        $matchingComponent = null;

        foreach ($this->availableComponents as $component) {
            if ($component->matchesClass($className)) {
                $matchingComponent = $component;
                break;
            }
        }
        return $matchingComponent;
    }

    public function refreshAvailableComponents(bool $force = false): Collection
    {
        if (self::$inprogress === false && ($this->availableComponents->isEmpty() || $force)) {
            self::$inprogress = true;
            $command = $this->getRunner() . ' snippets ' . getcwd();

            if ($result = shell_exec($command)) {
                try {
                    $time_start = microtime(true);
                    $decoded = json_decode($result, true, JSON_THROW_ON_ERROR);
                    if (!is_array($decoded)) {
                        return $this->availableComponents;
                    }
                    // @todo: Merge these as it is wasting computing power by looping twice.
                    $this->availableComponents = $this->getComponentsFromData($decoded);
                    $this->availableDirectives = $this->getDirectivesFromData($decoded);
                    Logger::logdbg('Components ready.');
                    self::$inprogress = false;
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
        foreach ($data['directives'] as $item) {
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
        foreach ([...$data['livewire'] ?? [], ...$data['blade'] ?? []] as $item) {
            if (in_array($item['type'], [SnippetDto::TYPE_COMPONENT, SnippetDto::TYPE_LIVEWIRE])) {
                $collection->add(new BladeComponentData(
                    name: $item['name'],
                    altName: $item['altName'],
                    file: !empty($item['file']) ? $item['file'] : null,
                    class: !empty($item['class']) ? $item['class'] : null,
                    doc: !empty($item['doc']) ? $item['doc'] : null,
                    views: $item['views'] ?? [],
                    type: $item['type'],
                    livewire: $item['type'] === SnippetDto::TYPE_LIVEWIRE,
                    arguments: $item['arguments'],
                    wireProps: $item['wireProps'] ?? [],
                    wireMethods: $item['wireMethods'] ?? [],
                    hasSlot: $item['hasSlot'] ?? false,
                ));
            }
        }
        return $collection;
    }
}
