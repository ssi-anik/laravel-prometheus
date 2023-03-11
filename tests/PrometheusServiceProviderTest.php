<?php

namespace Anik\Laravel\Prometheus\Test;

use Anik\Laravel\Prometheus\PrometheusManager;
use Anik\Laravel\Prometheus\Providers\PrometheusServiceProvider;
use Illuminate\Support\ServiceProvider;

class PrometheusServiceProviderTest extends TestCase
{
    public function testConfigurationFileIsAddedToPublishesArray()
    {
        $this->assertArrayHasKey(PrometheusServiceProvider::class, ServiceProvider::$publishes);
    }

    public function testPrometheusManagerIsBoundToContainer()
    {
        $this->assertInstanceOf(PrometheusManager::class, $this->app->make(PrometheusManager::class));
    }

    public function testPrometheusFacadeIsBoundToContainer()
    {
        $this->assertInstanceOf(PrometheusManager::class, $this->app->make('prometheus'));
    }
}
