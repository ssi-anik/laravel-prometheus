<?php

namespace Anik\Laravel\Prometheus\Providers;

use Anik\Laravel\Prometheus\Controllers\MetricController;
use Anik\Laravel\Prometheus\Listeners\ResponseReceivedListener;
use Anik\Laravel\Prometheus\Middlewares\PrometheusMiddleware;
use Anik\Laravel\Prometheus\PrometheusManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\ServiceProvider;

class PrometheusServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->publishAndMergeConfig();
        $this->enableMetricsExportRoute();
        $this->enableRequestResponseMetrics();
        $this->enableHttpMetrics();
    }

    public function register(): void
    {
        $this->registerBindings();
    }

    public function provides(): array
    {
        return [
            'prometheus',
            PrometheusManager::class,
            PrometheusMiddleware::class,
        ];
    }

    protected function isLumen(): bool
    {
        return false;
    }

    protected function publishAndMergeConfig()
    {
        $path = realpath(__DIR__ . '/../config/prometheus.php');

        if ($this->app->runningInConsole() && !$this->isLumen()) {
            $this->publishes([$path => config_path('prometheus.php'),]);
        }

        $this->mergeConfigFrom($path, 'prometheus');
    }

    protected function enableMetricsExportRoute(): void
    {
        $config = config('prometheus.export');

        if (false === ($config['enabled'] ?? true)) {
            return;
        }

        $this->addExportRouteToRouter($config);
    }

    protected function addExportRouteToRouter($config)
    {
        $this->app['router']->group(
            $config['attributes'] ?? [],
            function ($route) use ($config) {
                $route->addRoute(
                    $config['method'] ?? 'GET',
                    $config['path'] ?? '/metrics',
                    [
                        'as' => $config['as'] ?? 'laravel.prometheus.export',
                        'uses' => MetricController::class,
                    ]
                );
            });
    }

    protected function enableRequestResponseMetrics(): void
    {
        $config = config('prometheus.request');

        if (false === ($config['enabled'] ?? true)) {
            return;
        }

        $this->app->singleton(PrometheusMiddleware::class, fn() => new PrometheusMiddleware());

        $this->addTerminableMiddlewareToRouter();
    }

    protected function enableHttpMetrics(): void
    {
        $config = config('prometheus.http');

        if (!($config['enabled'] ?? false) || !$this->app->bound('events')) {
            return;
        }

        $this->app['events']->listen(ResponseReceived::class, ResponseReceivedListener::class);
    }

    protected function addTerminableMiddlewareToRouter()
    {
        $this->app[Kernel::class]->pushMiddleware(PrometheusMiddleware::class);
    }

    protected function registerBindings(): void
    {
        $this->app->singleton(PrometheusManager::class, fn($app) => new PrometheusManager($app));
        $this->app->bind('prometheus', fn($app) => $app->make(PrometheusManager::class));
    }
}
