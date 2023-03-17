<?php

namespace Anik\Laravel\Prometheus\Extractors;

class LumenRequest extends Request
{
    protected function getRouteAction(): array
    {
        $route = $this->request->route();
        if ($route && ($route[1] ?? null)) {
            return $route[1];
        }

        return [];
    }
}
