<?php

namespace Anik\Laravel\Prometheus\Extractors;

class LumenHttpRequest extends HttpRequest
{
    protected function getRouteAction(): array
    {
        return $this->request->route()[1] ?? [];
    }
}
