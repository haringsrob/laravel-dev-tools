<?php

namespace App;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Logger
{
    public static function getLogger(): LoggerInterface
    {
        return new NullLogger();
    }

    public static function logException(\Exception $e): void
    {
        $message = $e->getMessage();
        $line = $e->getLine();
        $file = $e->getFile();

        self::logdbg($message . ' IN . ' . $file . ' ON LINE ' . $line);
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
