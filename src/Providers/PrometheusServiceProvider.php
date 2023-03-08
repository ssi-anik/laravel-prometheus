<?php

namespace Anik\Laravel\Prometheus\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class PrometheusServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->publishAndMergeConfig();
    }

    protected function publishAndMergeConfig()
    {
        $path = realpath(__DIR__.'/../config/prometheus.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([$path => config_path('prometheus.php'),]);
        }

        $this->mergeConfigFrom($path, 'prometheus');
    }
}
