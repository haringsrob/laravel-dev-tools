<?php

use Illuminate\Support\Facades\Artisan;

Artisan::call('route:list --json');
echo Artisan::output();
