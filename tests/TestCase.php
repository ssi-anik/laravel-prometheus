<?php

namespace Anik\Laravel\Prometheus\Test;

use Anik\Laravel\Prometheus\Providers\PrometheusServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PrometheusServiceProvider::class,
        ];
    }
}
