<?php

namespace Anik\Laravel\Prometheus\Providers;

use Anik\Laravel\Prometheus\Extractors\LumenHttpRequest;
use Anik\Laravel\Prometheus\Extractors\HttpRequest;
use Anik\Laravel\Prometheus\Middlewares\PrometheusMiddleware;

class LumenPrometheusServiceProvider extends PrometheusServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->app->bind(HttpRequest::class, fn($app, $args) => new LumenHttpRequest(...array_values($args)));
    }

    protected function addTerminableMiddlewareToRouter()
    {
        $this->app->middleware(PrometheusMiddleware::class);
    }

    public function provides(): array
    {
        return array_merge(parent::provides(), [
            HttpRequest::class,
        ]);
    }
}
