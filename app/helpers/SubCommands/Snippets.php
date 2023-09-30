<?php

use App\Dto\Component;
use App\Dto\Directive;
use App\Logger;
use App\Reflection\ReflectionClass;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Support\Facades\File;
use Illuminate\View\Factory;
use Symfony\Component\VarDumper\VarDumper;

include_once(__DIR__ . '/../../Dto/Snippet.php');
include_once(__DIR__ . '/../../Dto/SnippetDto.php');
include_once(__DIR__ . '/../../Dto/Directive.php');
include_once(__DIR__ . '/../../Dto/Component.php');
include_once(__DIR__ . '/../../helpers/invade.php');
include_once(__DIR__ . '/../../Reflection/ReflectionClass.php');
include_once(__DIR__ . '/../../Reflection/ReflectionMethod.php');
include_once(__DIR__ . '/../../Reflection/StringHelper.php');

$GLOBALS['viewUsageMapping'] = [];

/**
 * Execute the console command.
 *
 * @return mixed
 */
function handle()
{
    $mapping = [];
    $arrayFinal = [];

    $arrayFinal['viewUsageMapping'] = $viewUsageMapping = getAllViewsUsageMap();

    foreach (getHinted() as $final) {
        $arrayFinal['blade'][$final->name] = $final->toArray($viewUsageMapping);

        foreach ($final->views as $view) {
            $mapping[$view] = $final->name;
        }
    }

    foreach (getDirectives() as $final) {
        $arrayFinal['directives'][$final->name] = $final->toArray();
    }

    foreach (getLivewireComponents() as $final) {
        $arrayFinal['livewire'][$final->name] = $final->toArray($viewUsageMapping);

        foreach ($final->views as $view) {
            $mapping[$view] = $final->name;
        }
    }

    foreach (getBladeComponents() as $final) {
        $arrayFinal['blade'][$final->name] = $final->toArray($viewUsageMapping);

        foreach ($final->views as $view) {
            $mapping[$view] = $final->name;
        }
    }

    $arrayFinal['mapping'] = $mapping;

    echo json_encode($arrayFinal);
}

/**
 * @return Component[]
 */
function getLivewireComponents(): array
{
    $data = [];

    // Livewire v3.
    if (class_exists(\Livewire\Mechanisms\ComponentRegistry::class)) {
        $registry = app()->make(\Livewire\Mechanisms\ComponentRegistry::class);

        $paths = [base_path('app/Http/Livewire'), base_path('app/Livewire')];

        foreach ($paths as $path) {
            $disk = \Illuminate\Support\Facades\Storage::build([
                'driver' => 'local',
                'root' => $path,
            ]);

            foreach ($disk->allFiles() as $file) {
                // Conver the filename to a "component name".
                $file = str_replace('.php', '', $file);
                $name = invade($registry)->classToName($file);
                $class = invade($registry)->nameToClass($name);

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
    // Livewire v2. Not sure if this still works.
    elseif (class_exists(\Livewire\LivewireComponentsFinder::class)) {
        try {
            $livewire = app('livewire');
        } catch (Exception $e) {
            return [];
        }

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
        if (File::exists(base_path('app/Livewire'))) {
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

function getHinted() {
    /** @var Factory $view */
    $view = app()->make('view');
    $finder = $view->getFinder();
        $list = [];
    foreach (invade($finder)->hints as $key => $paths) {
        $prefix = 'x-' . $key . '::';

        foreach ($paths as $path) {

            $files = File::allFiles(realpath($path));

            foreach ($files as $file) {
                if (str_ends_with($file->getPathname(), '.blade.php')) {
                    if ($file->getFilename() === 'index.blade.php') {
                        $name = Str::afterLast($file->getPath(), '/');
                        $list[$prefix . $name] = $file->getPathname();
                    } else {
                        $cleanedPath = str_replace([realpath($path) . '/', '.blade.php'], '', $file->getPathname());

                        if (str_starts_with($cleanedPath, 'components/')) {
                            $list[$prefix . str_replace('/', '.', Str::replaceFirst('components/', '', $cleanedPath))] = $file->getPathname();
                        }
                    }
                }
            }
        }

    }
    $data = [];

    foreach ($list as $name => $file) {
        $data[] = new Component(
            name: $name,
            file: $file,
            views: [$file],
        );
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

function getAllViewsUsageMap(?string $path = null): array
{
    $list = [];
    if (!$path) {
        $path = base_path('app');
    }

    $dir = new RecursiveDirectoryIterator($path);
    foreach (new RecursiveIteratorIterator($dir) as $filename => $file) {
        if (is_dir($file)) {
            continue;
        }
        $content = file_get_contents($file->getPathname());

        $matches = [];
        preg_match_all('/view\((?:\'|")([\w\-:.]*)(?:\'|")(,|\))/', $content, $matches, PREG_OFFSET_CAPTURE);

        if ($matches[0] === []) {
            continue;
        }

        foreach ($matches[1] as $match) {
            $list[$match[0]][] = [
                'file' => $file->getpathname(),
                'pos' => $match[1]
            ];
        }
    }

    return $list;
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
    try {
        class_exists($class);
    } catch(Throwable) {
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
    try {
        $class = new \ReflectionClass($class);
        return $class->getFileName();
    }
    catch (Throwable) {
        return null;
    }
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
