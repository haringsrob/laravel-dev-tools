<?php

namespace App\Reflection;

use ReflectionMethod as GlobalReflectionMethod;

class ReflectionMethod extends GlobalReflectionMethod
{
    public $body;
    public $filename;

    public function __construct($objectOrMethod, $method = null, $filename = null)
    {
        parent::__construct($objectOrMethod, $method);
        $this->filename = $filename;
        $this->body = $this->getBody();
    }

    private function getBody()
    {
        $file = file_get_contents($this->filename);

        $methodNameStartPos = mb_strpos($file, "function $this->name");
        $methodNameEndPos = $methodNameStartPos + mb_strlen("function $this->name");
        $paramsEndPos = $methodNameEndPos;

        for ($i = $methodNameEndPos; $i < mb_strlen($file); $i++) {
            $c = mb_substr($file, $i, 1);
            if ($c === '(') {
                $paramsEndPos = StringHelper::getClosingBracePos($file, $i);
                break;
            }
        }

        for ($i = $paramsEndPos; $i < mb_strlen($file); $i++) {
            $c = mb_substr($file, $i, 1);
            if ($c === '{') {
                return StringHelper::trimHereDoc(StringHelper::getBodyInsideBraces($file, $i));
            }
        }

        return null;
    }
}
