<?php

namespace Anik\Laravel\Prometheus\Providers;

use Anik\Laravel\Prometheus\Extractors\LumenRequest;
use Anik\Laravel\Prometheus\Extractors\Request;
use Anik\Laravel\Prometheus\Middlewares\PrometheusMiddleware;

class LumenPrometheusServiceProvider extends PrometheusServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->app->bind(Request::class, fn($app, ...$args) => new LumenRequest(...$args));
    }

    protected function addTerminableMiddlewareToRouter()
    {
        $this->app->middleware(PrometheusMiddleware::class);
    }

    public function provides(): array
    {
        return array_merge(parent::provides(), [
            Request::class,
        ]);
    }
}
