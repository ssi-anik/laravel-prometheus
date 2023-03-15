<?php

namespace Anik\Laravel\Prometheus;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Route;

class RequestIdentifier
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function parse($request)
    {
        $route = $request->route();
        if ($route instanceof Route) {
            $route = $route->getAction();
        }

        return $route['as'] ?? $route['uses'] ?? $request->url();


    }
}
