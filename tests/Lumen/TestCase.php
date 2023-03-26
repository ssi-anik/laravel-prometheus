<?php

namespace Anik\Laravel\Prometheus\Test\Lumen;

use Anik\Laravel\Prometheus\Providers\LumenPrometheusServiceProvider;
use Anik\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function serviceProviders(): array
    {
        return [
            LumenPrometheusServiceProvider::class,
        ];
    }
}
