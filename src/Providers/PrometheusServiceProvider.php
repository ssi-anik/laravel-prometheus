<?php

namespace Anik\Laravel\Prometheus\Providers;

use Anik\Laravel\Prometheus\Controllers\MetricController;
use Anik\Laravel\Prometheus\Middlewares\PrometheusMiddleware;
use Anik\Laravel\Prometheus\PrometheusManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class PrometheusServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->publishAndMergeConfig();
        $this->enableMetricsExportRoute();
        $this->enableRequestResponseMetrics();
    }

    protected function publishAndMergeConfig()
    {
        $path = realpath(__DIR__ . '/../config/prometheus.php');

        if ($this->app->runningInConsole() && !Str::contains($this->app->version(), 'Lumen')) {
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

    protected function addTerminableMiddlewareToRouter()
    {
        $this->app[Kernel::class]->pushMiddleware(PrometheusMiddleware::class);
    }

    public function register(): void
    {
        $this->registerManagers();
        $this->registerFacades();
    }

    protected function registerManagers(): void
    {
        $this->app->singleton(PrometheusManager::class, fn($app) => new PrometheusManager($app));
    }

    protected function registerFacades(): void
    {
        $this->app->bind('prometheus', fn($app) => $app->make(PrometheusManager::class));
    }

    public function provides(): array
    {
        return [
            'prometheus',
            PrometheusManager::class,
            PrometheusMiddleware::class,
        ];
    }
}
