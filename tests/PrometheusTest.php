<?php

namespace Anik\Laravel\Prometheus\Test;

use Anik\Laravel\Prometheus\Facades\Prometheus;
use Anik\Laravel\Prometheus\Metric;
use Prometheus\Storage\InMemory;

class PrometheusTest extends TestCase
{
    public function testFacadeIsRegisteredSuccessfully()
    {
        $this->assertInstanceOf(InMemory::class, Prometheus::adapter('memory'));
        $this->assertInstanceOf(Metric::class, Prometheus::metric('memory'));
    }
}
