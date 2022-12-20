<?php

/**
 * Currently unused by this package, but I already have wip for:
 * config('<autocomplete'>);
 */

function handle()
{
    $array = [];
    foreach (app()->getBindings() as $key => $value) {
        $fun = new \ReflectionFunction($value['concrete']);
        $array[$key] = $fun->getClosureScopeClass()->getName();
    }
    echo json_encode($array);
}

handle();
