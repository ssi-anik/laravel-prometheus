<?php

namespace Anik\Laravel\Prometheus;

use Illuminate\Support\Facades\Facade;

class Prometheus extends Facade
{
    public static function getFacadeRoot(): string
    {
        return 'prometheus';
    }
}
