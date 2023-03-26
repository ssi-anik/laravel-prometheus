<?php

namespace Anik\Laravel\Prometheus\Extractors;

class LumenRequest extends Request
{
    protected function getRouteAction(): array
    {
        return $this->request->route()[1] ?? [];
    }
}
