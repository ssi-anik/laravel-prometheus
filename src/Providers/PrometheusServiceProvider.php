<?php

namespace Anik\Laravel\Prometheus\Providers;

use Anik\Laravel\Prometheus\Controllers\MetricController;
use Anik\Laravel\Prometheus\PrometheusManager;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class PrometheusServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->publishAndMergeConfig();
        $this->enableExportRoute();
    }

    protected function publishAndMergeConfig()
    {
        $path = realpath(__DIR__ . '/../config/prometheus.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([$path => config_path('prometheus.php'),]);
        }

        $this->mergeConfigFrom($path, 'prometheus');
    }

    protected function enableExportRoute(): void
    {
        $config = config('prometheus.export');

        if (false === ($config['enabled'] ?? true)) {
            return;
        }

        $this->app['router']->group(
            $config['attributes'] ?? [],
            function ($route) use ($config) {
                $route->addRoute(
                    $config['method'] ?? 'GET',
                    $config['path'] ?? '/metrics',
                    ['as' => 'laravel.prometheus.export', 'uses' => MetricController::class]
                );
            });
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
        ];
    }
}
