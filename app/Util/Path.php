<?php

namespace App\Util;

class Path
{
    public static function getBaseDir(): string
    {
        // Run native: ../../
        // Run phar: ../../../
        return __DIR__ . '/../../';
    }
}
