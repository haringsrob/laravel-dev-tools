<?php

namespace App;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Logger
{
    public static function getLogger(): LoggerInterface
    {
        return new \Wa72\SimpleLogger\FileLogger('/Users/rob/Sites/laravel-dev-generators/log.txt');
        return new NullLogger();
        // The below is usefull for development.
        // @todo: Use this via a switc?
        // return new \Wa72\SimpleLogger\FileLogger('path to log.txt');
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
