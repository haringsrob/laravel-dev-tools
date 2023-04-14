<?php

use App\Dto\Component;
use App\Dto\Directive;
use App\Reflection\ReflectionClass;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Support\Facades\File;
use function Safe\file_get_contents;

include_once(__DIR__ . '/../../Dto/Snippet.php');
include_once(__DIR__ . '/../../Dto/SnippetDto.php');
include_once(__DIR__ . '/../../Dto/Directive.php');
include_once(__DIR__ . '/../../Dto/Component.php');
include_once(__DIR__ . '/../../helpers/invade.php');
include_once(__DIR__ . '/../../Reflection/ReflectionClass.php');
include_once(__DIR__ . '/../../Reflection/ReflectionMethod.php');
include_once(__DIR__ . '/../../Reflection/StringHelper.php');

/**
 * Execute the console command.
 *
 * @return mixed
 */
function handle()
{
    $arrayFinal = [];
    foreach (getDirectives() as $final) {
        $arrayFinal[$final->name] = $final->toArray();
    }

    foreach (getLivewireComponents() as $final) {
        $arrayFinal[$final->name] = $final->toArray();
    }

    foreach (getBladeComponents() as $final) {
        $arrayFinal[$final->name] = $final->toArray();
    }

    echo json_encode($arrayFinal, JSON_PRETTY_PRINT);
}

/**
 * @return Component[]
 */
function getLivewireComponents(): array
{
    $data = [];
    if (class_exists(\Livewire\LivewireComponentsFinder::class)) {
        try {
            $livewire = app('livewire');
        } catch (Exception) {
            return [];
        }

        // Todo
        $livewireAliased = $livewire->getComponentAliases();

        if (File::exists(base_path('app/Http/Livewire'))) {
            $livewireComponentFinder = app(\Livewire\LivewireComponentsFinder::class);
            foreach ($livewireComponentFinder->getManifest() as $name => $class) {
                $data[] = new Component(
                    name: "livewire:$name",
                    file: getClassFile($class),
                    class: $class,
                    views: extractViewNames($class),
                    livewire: true
                );
            }
        }
    }

    return $data;
}

/**
 * @return Directive[]
 */
function getDirectives(): array
{
    $directivesList = [];
    $directives = app('blade.compiler')->getCustomDirectives();
    /** @var \Closure $closure */
    foreach ($directives as $name => $closure) {
        if (strpos($name, 'end') === 0) {
            continue;
        }

        if ($closure instanceof \Closure) {
            $r = new ReflectionFunction($closure);
        } else {
            $class = $closure[0] ?? null;
        }

        $directiveObj = new Directive();
        $directiveObj->name = $name;

        if (isset($r) ?? false && $r->getClosureScopeClass()) {
            $directiveObj->class = $r->getClosureScopeClass()->name;
            $directiveObj->file = $r->getClosureScopeClass()->getFileName();
            $directiveObj->line = $r->getStartLine();
        } else {
            $directiveObj->class = $class;
            $directiveObj->file = getClassFile($class);
        }

        $directivesList[$name] = $directiveObj;
    }
    // EndDirectives
    foreach (array_keys($directives) as $name) {
        if (strpos($name, 'end') === 0) {
            $name = ltrim($name, 'end');
            if (isset($directivesList[$name])) {
                $directivesList[$name]->hasEnd = true;
            }
            if (isset($directivesList[lcfirst($name)])) {
                $directivesList[lcfirst($name)]->hasEnd = true;
            }
        }
    }

    return $directivesList;
}

/**
 * @return Component[]
 */
function getBladeComponents(): array
{
    $data = [];

    $blade = getBlade();
    $tagCompiler = new \Illuminate\View\Compilers\ComponentTagCompiler(
        $blade->getClassComponentAliases(),
        $blade->getClassComponentNamespaces(),
        $blade
    );

    // Get all clases in a namespace.
    // The below is a rather complex way to figure out all registered components. But hey, it works!
    foreach ($blade->getClassComponentNamespaces() as $key => $namespace) {
        // Figure out the base path of the namespace.
        $exploded = explode('\\', $namespace);
        $baseNamespace = implode('\\', array_slice($exploded, 0, 2));
        $classes = classesInNamespace($baseNamespace);

        $serviceProvider = null;
        foreach ($classes as $className) {
            // We prefer to use the PackageNameServiceProvider.
            if (str_contains($className, $exploded[0] . 'ServiceProvider')) {
                $serviceProvider = $baseNamespace . '\\' . $className;
                break;
            }
            if (str_contains($className, 'ServiceProvider')) {
                $serviceProvider = $baseNamespace . '\\' . $className;
                break;
            }
        }

        if (!$serviceProvider) {
            // No service provider was found.
            continue;
        }

        $reflection = new ReflectionClass($serviceProvider);

        $explodedPath = explode(DIRECTORY_SEPARATOR, $reflection->getFileName());
        array_pop($explodedPath);
        $path = implode(DIRECTORY_SEPARATOR, $explodedPath);

        $pathToLoad = $path . str_replace('\\', DIRECTORY_SEPARATOR, str_replace($baseNamespace, '', $namespace));

        $iterator = new RecursiveDirectoryIterator($pathToLoad, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($iterator);
        foreach ($files as $file) {
            require_once $pathToLoad . DIRECTORY_SEPARATOR . $file->getFileName();
        }

        $classes = classesInNamespace($namespace);

        foreach ($classes as $class) {
            $fileOrClass = $namespace . '\\' . $class;
            $splitted = explode('/', $class);
            $name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', array_pop($splitted)));

            $data[] = new Component(
                name: "x-$key::$name",
                file: getClassFile($fileOrClass),
                views: extractViewNames($fileOrClass),
                class: $fileOrClass,
            );
        }
    }

    // Get the components from the folder.
    $viewsFiles = getViewsFiles();
    foreach ($viewsFiles as $path => $viewName) {
        if (str_starts_with($viewName[0], 'livewire.')) {
            // Not handled here.
            continue;
        }
        if (str_starts_with($viewName[0], 'components.')) {
            // @todo: Ideally only replaces the first occurance.
            $name = str_replace('components.', '', $viewName[0]);
            $altName = str_replace('components-', '', $viewName[1]);

            $className = $tagCompiler->guessClassName($name);

            $data[] = new Component(
                name: "x-$name",
                altName: "x-$altName",
                file: class_exists($className) ? getClassFile($className) : $path,
                class: class_exists($className) ? $className : null,
                views: class_exists($className) ? [$viewName[0]] : []
            );
        } else {
            $data[] = new Component(
                name: $viewName[0],
                file: $path,
                views: [],
                simpleView: true
            );
        }
    }

    // Aliased.
    $aliased = $blade->getClassComponentAliases();
    foreach ($aliased as $name => $fileOrClass) {
        if (strpos($fileOrClass, '\\') !== false) {
            $data[] = new Component(
                name: "x-$name",
                file: getClassFile($fileOrClass),
                views: extractViewNames($fileOrClass),
                class: $fileOrClass,
            );
        } else {
            $data[] = new Component(
                name: "x-$name",
                views: [$fileOrClass],
            );
        }
    }

    return $data;
}

function classesInNamespace($namespace)
{
    $namespace .= '\\';
    $myClasses  = array_filter(get_declared_classes(), function ($item) use ($namespace) {
        return substr($item, 0, strlen($namespace)) === $namespace;
    });
    $theClasses = [];
    foreach ($myClasses as $class) {
        $theParts = explode('\\', $class);
        $theClasses[] = end($theParts);
    }
    return $theClasses;
}

function getViewsFiles(): array
{
    $list = [];
    foreach (config('view.paths') as $viewPath) {
        $files = File::allFiles($viewPath);

        foreach ($files as $file) {
            if (str_ends_with($file->getPathname(), '.blade.php')) {
                $relativeToProject = str_replace(getcwd(), '', $file->getPathname());
                $cleanedPath = str_replace(['/resources/views/', '.blade.php'], '', $relativeToProject);

                $list[$file->getPathname()] = [
                    str_replace('/', '.', $cleanedPath),
                    str_replace('/', '-', $cleanedPath)
                ];
            }
        }
    }

    return $list;
}

/**
 * @return string[]
 */
function extractViewNames(string $class): array
{
    if (!class_exists($class)) {
        return [];
    }
    $class = new \App\Reflection\ReflectionClass($class);
    try {
        $matches = [];
        preg_match_all('/view\((?:\'|")([\w\-:.]*)(?:\'|")(,|\))/', file_get_contents($class->getFileName()), $matches);
        return $matches[1] ?? [];
    } catch (ReflectionException) {
        return [];
    }
}

function getBlade(): BladeCompiler
{
    return app('blade.compiler');
}

function getClassFile(string $class): ?string
{
    if (class_exists($class)) {
        $class = new \ReflectionClass($class);
        return $class->getFileName();
    }
    return null;
}

function getPossibleAttributes(string $class): array
{
    // @todo: Read inherited.
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

handle($options);
