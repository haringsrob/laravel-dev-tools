<?php

namespace App\Reflection;

use ReflectionClass as GlobalReflectionClass;
use App\Reflection\ReflectionMethod;

class ReflectionClass extends GlobalReflectionClass
{
    public function getMethod($name)
    {
        $method = parent::getMethod($name);
        $extMethod = new ReflectionMethod($method->class, $method->name, $this->getFileName());
        return $extMethod;
    }
}
