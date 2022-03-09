<?php

/**
 * Currently unused by this package, but I already have wip for:
 * view('<autocomplete'>);
 */

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

function handle()
{
    $filesystem = new Filesystem();
    $views = [];
    foreach (app('view')->getFinder()->getPaths() as $path) {
        $views = array_merge($views, getViews($path, $filesystem));
    }
    foreach (app('view')->getFinder()->getHints() as $namespace => $paths) {
        foreach ($paths as $path) {
            $views = array_merge($views, getViews($path, $filesystem, $namespace, '::'));
        }
    }
    echo json_encode($views);
}

function getViewsFromDirectory($directories, $filesystem, $parentDirectory = null)
{
    $views = [];
    foreach ($directories as $directory) {
        $viewDirectory = basename($directory);
        if ($parentDirectory) {
            $viewDirectory = $parentDirectory . '.' . $viewDirectory;
        }
        if ($filesystem->directories($directory)) {
            $childDirectoryViews = getViewsFromDirectory(
                $filesystem->directories($directory),
                $filesystem,
                $viewDirectory
            );
            if ($childDirectoryViews) {
                $views[] = $childDirectoryViews;
            }
        }
        foreach ($filesystem->files($directory) as $file) {
            if (!is_object($file) || !method_exists($file, 'getBaseName')) {
                continue;
            }
            if (strpos($file->getBaseName(), '.blade.php')) {
                $fileName = str_replace('.blade.php', '', $file->getBaseName());
                $views[] = $viewDirectory . '.' . $fileName;
            }
        }
    }
    return $views;
}

function getViews($path, $filesystem, $parentDirectory = null, $deluminator = '.')
{
    $views = [];
    foreach ($filesystem->files($path) as $file) {
        if (!is_object($file) || !method_exists($file, 'getBaseName')) {
            continue;
        }
        if (strpos($file->getBaseName(), '.blade.php')) {
            $fileName = str_replace('.blade.php', '', $file->getBaseName());
            $view = '';
            if ($parentDirectory) {
                $view = $parentDirectory . $deluminator;
            }
            $view .= $fileName;
            $views = array_merge($views, [$view]);
        }
    }
    return array_merge(
        $views,
        Arr::flatten(getViewsFromDirectory($filesystem->directories($path), $filesystem))
    );
}

handle();
