<?php

namespace App\Reflection;

use Exception;

class StringHelper
{
    private static $braces = [
        '[' => ']',
        '{' => '}',
        '(' => ')',
        '<' => '>',
    ];

    public static function getOpeningBraces()
    {
        return array_keys(self::$braces);
    }

    public static function getClosingBraces()
    {
        return array_values(self::$braces);
    }

    public static function getClosingBracePair($brace)
    {
        if (!in_array($brace, self::getOpeningBraces())) {
            throw new Exception("$brace isn't an open brace", 400);
        }

        return self::$braces[$brace];
    }

    public static function getClosingBracePos($s, $pos)
    {
        $brace = mb_substr($s, $pos, 1);

        if (!in_array($brace, self::getOpeningBraces())) {
            throw new Exception("$brace isn't a brace", 401);
        }

        $closingBrace = self::getClosingBracePair($brace);
        $stack = [];

        for ($i = $pos + 1; $i < mb_strlen($s); $i++) {
            $c = mb_substr($s, $i, 1);

            if ($c === $closingBrace) {
                if (empty($stack)) {
                    return $i;
                } else {
                    array_pop($stack);
                }
            } elseif ($c === $brace) {
                array_push($stack, $c);
            }
        }

        return null;
    }

    public static function getBodyInsideBraces($s, $pos)
    {
        $startPos = $pos;
        $endPos = self::getClosingBracePos($s, $pos);

        $body = mb_substr($s, $startPos + 1, $endPos - $startPos - 1);

        return rtrim(ltrim($body, "\n\r"), " \t\n\r");
    }

    public static function trimHereDoc($s)
    {
        return implode(" ", array_map('trim', explode("\n", $s)));
    }

    public static function trimSpacesAfterBraces($s)
    {
        return preg_replace('/\(\s+/', '(', $s);
    }

    public static function trimSpacesBeforeBraces($s)
    {
        return preg_replace('/\s+\)/', ')', $s);
    }

    public static function trimSql($sql)
    {
        $sql = self::trimHereDoc($sql);
        $sql = self::trimSpacesAfterBraces($sql);
        return self::trimSpacesBeforeBraces($sql);
    }

    public static function camel2id($name)
    {
        $regex = '/(?<!\p{Lu})\p{Lu}/u';
        return strtolower(trim(preg_replace($regex, '_\0', $name), '_'));
    }

    public static function id2camel($id)
    {
        return implode('', array_map(
            function ($part) {
                return ucfirst($part);
            },
            explode('_', $id)
        ));
    }
}
