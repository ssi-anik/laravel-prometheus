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
    use InteractsWithStorage;

    public static function metricTypeMethodDataProvider(): array
    {
        return [
            'counter' => [
                [
                    'method' => 'counter',
                    'expected' => Counter::class,
                ],
            ],
            'histogram' => [
                [
                    'method' => 'histogram',
                    'expected' => Histogram::class,
                ],
            ],
            'gauge' => [
                [
                    'method' => 'gauge',
                    'expected' => Gauge::class,
                ],
            ],
            'summary' => [
                [
                    'method' => 'summary',
                    'expected' => Summary::class,
                ],
            ],
        ];
    }

    /** @dataProvider metricTypeMethodDataProvider */
    public function testMethodsReturnCorrectCollectorType(array $data)
    {
        $method = $data['method'];
        $expected = $data['expected'];

        $adapter = $this->app->make(PrometheusManager::class)->adapter('memory');

        $metric = new Metric($adapter);

        $collector = $metric->$method('name');
        $collector->skip();

        $this->assertInstanceOf($expected, $collector);
    }

    /** @dataProvider metricTypeMethodDataProvider */
    public function testMethodsPassesAdapterAndNamespaceToCollector(array $data)
    {
        $method = $data['method'];

        $adapter = $this->app->make(PrometheusManager::class)->adapter('memory');

        $metric = new Metric($adapter, $namespace = '__NAMESPACE_');
        $collector = $metric->$method('name');
        $collector->skip();

        $this->assertInstanceOf(InMemory::class, $collector->getAdapter());
        $this->assertSame($namespace, $collector->getNamespace());
    }
}
