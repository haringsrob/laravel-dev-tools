<?php

/**
 * Currently unused by this package, but I already have wip for:
 * config('<autocomplete'>);
 */

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;

function handle()
{
    echo json_encode(Arr::dot(Config::all()));
}

handle();
