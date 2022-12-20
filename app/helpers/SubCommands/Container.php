<?php

use Illuminate\Support\Arr;

/**
 * Currently unused by this package, but I already have wip for:
 * config('<autocomplete'>);
 */

function handle()
{
    $array = [];
    $aliases = invade(app())->aliases;
    foreach (invade(app())->abstractAliases as $key => $targets) {
        if (str_contains($key, '\\')) {
            $originalKey = $key;

            foreach ($aliases as $alias => $value) {
                if ($value === $key) {
                    $key = $alias;
                    break;
                }
            }

            $array[$key] = $originalKey;
        }
        else {
            $array[$key] = reset($targets);
        }
    }
    echo json_encode($array);
}

handle();
