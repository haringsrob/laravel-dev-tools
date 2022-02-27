<?php

function handle()
{
    echo json_encode(Arr::dot(Config::all()));
}

handle();
