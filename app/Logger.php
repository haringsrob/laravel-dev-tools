<?php

namespace App;

use Psr\Log\LoggerInterface;

class Logger
{
    public static function getLogger(): LoggerInterface
    {
        return new \Wa72\SimpleLogger\FileLogger(__DIR__ . '/../log.txt');
    }

    public static function logdbg($data): void
    {
        ob_start();
        var_dump($data);
        $content = ob_get_contents();
        ob_end_clean();

        self::getLogger()->debug($content);
    }
}
