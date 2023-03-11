<?php

namespace Anik\Laravel\Prometheus\Test;

use Anik\Laravel\Prometheus\Collector\Counter;
use Anik\Laravel\Prometheus\Collector\Gauge;
use Anik\Laravel\Prometheus\Collector\Histogram;
use Anik\Laravel\Prometheus\Collector\Summary;
use Anik\Laravel\Prometheus\Metric;
use Anik\Laravel\Prometheus\PrometheusManager;
use Prometheus\Storage\InMemory;

class MetricTest extends TestCase
{
    public static function metricTypeMethodDataProvider(): array
    {
        return [
            'counter' => ['counter', Counter::class],
            'histogram' => ['histogram', Histogram::class],
            'gauge' => ['gauge', Gauge::class],
            'summary' => ['summary', Summary::class],
        ];
    }

    /** @dataProvider metricTypeMethodDataProvider */
    public function testMethodsReturnCorrectCollectorType(string $method, string $expected)
    {
        $adapter = $this->app->make(PrometheusManager::class)->adapter('memory');

        $metric = new Metric($adapter);

        $this->assertInstanceOf($expected, $metric->$method('name'));
    }

    /** @dataProvider metricTypeMethodDataProvider */
    public function testMethodsPassesAdapterAndNamespaceToCollector(string $method)
    {
        $adapter = $this->app->make(PrometheusManager::class)->adapter('memory');

        $metric = new Metric($adapter, $namespace = '__NAMESPACE_');
        $collector = $metric->$method('name');

        $this->assertInstanceOf(InMemory::class, $collector->getAdapter());
        $this->assertSame($namespace, $collector->getNamespace());
    }
}
