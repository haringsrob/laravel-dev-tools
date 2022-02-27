<?php

use App\Dto\Component;
use App\Dto\Directive;
use Illuminate\Support\Facades\File;

include_once(__DIR__ . '/../../Dto/Snippet.php');
include_once(__DIR__ . '/../../Dto/SnippetDto.php');
include_once(__DIR__ . '/../../Dto/Directive.php');
include_once(__DIR__ . '/../../Dto/Component.php');
include_once(__DIR__ . '/../../helpers/invade.php');

/**
 * Execute the console command.
 *
 * @return mixed
 */
function handle()
{
    $blade = app('blade.compiler');

    // Done
    $directives = $blade->getCustomDirectives();
    $aliased = invade($blade)->classComponentAliases;

    // TODO
    $namespaced = invade($blade)->classComponentNamespaces;

    // TODO components in the view/components folder

    /** @var SnippetData $data */
    $data = [];

    $ignore = ['Illuminate\\View\\DynamicComponent'];

    $directivesList = [];

    // Directives
    foreach (array_keys($directives) as $name) {
        if (strpos($name, 'end') === 0) {
            continue;
        }
        $directiveObj = new Directive();
        $directiveObj->name = $name;
        $directivesList[$name] = $directiveObj;
    }
    // EndDirectives
    foreach (array_keys($directives) as $name) {
        if (strpos($name, 'end') === 0) {
            $name = ltrim($name, 'end');
            if ($directive = $directivesList[$name]) {
                $directive->hasEnd = true;
            }
        }
    }

    // Livewire
    if (class_exists(\Livewire\LivewireComponentsFinder::class)) {
        $livewire = app('livewire');


        // Todo
        $livewireAliased = $livewire->getComponentAliases();

        if (File::exists(base_path('app/Http/Livewire'))) {
            $livewireComponentFinder = app(\Livewire\LivewireComponentsFinder::class);
            foreach ($livewireComponentFinder->getManifest() as $name => $class) {
                $snippet = new Component();
                $snippet->livewire = true;
                $snippet->name = "livewire:$name";
                $snippet->file = $class;
                $snippet->arguments = getPossibleAttributes($class);
                $data[] = $snippet;
            }
        }
    }

    // Regular blade components
    foreach ($aliased as $name => $fileOrClass) {
        if (in_array($fileOrClass, $ignore)) {
            continue;
        }
        if (strpos($fileOrClass, '\\') !== false) {
            $snippet = new Component();
            $snippet->name = "x-$name";
            $snippet->file = $fileOrClass;
            $snippet->arguments = getPossibleAttributes($fileOrClass);
            $data[] = $snippet;
        } else {
            /* echo 'file' . $fileOrClass; */
        }
    }

    $arrayFinal = [];

    foreach ($directivesList as $final) {
        $arrayFinal[$final->name] = $final->toEntry();
    }

    foreach ($data as $final) {
        $arrayFinal[$final->name] = $final->toEntry();
    }

    echo json_encode($arrayFinal, JSON_PRETTY_PRINT);
}

function getPossibleAttributes(string $class): array
{
    $class = new \ReflectionClass($class);
    $result = [];
    /** @var \ReflectionProperty $attribute */
    foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $attribute) {
        $result[$attribute->getName()] = [
            'type' => $attribute->getType()?->getName(),
            'default' => $attribute->getDefaultValue(),
            'doc' => $attribute->getDocComment()
        ];
    }

    return $result;
}

handle();
