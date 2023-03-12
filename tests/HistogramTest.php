<?php

namespace Anik\Laravel\Prometheus\Test;

use Anik\Laravel\Prometheus\Collector\Histogram;
use Anik\Laravel\Prometheus\Exceptions\PrometheusException;
use Prometheus\CollectorRegistry;

class HistogramTest extends TestCase
{
    public static function namespaceDataProvider(): array
    {
        return [
            'should be empty string' => [
                'namespace' => null,
                'expected' => '',
            ],
            '__NAMESPACE__' => [
                'namespace' => '__NAMESPACE__',
                'expected' => '__NAMESPACE__',
            ],
        ];
    }

    /** @dataProvider namespaceDataProvider */
    public function testNamespace(?string $namespace, string $expected)
    {
        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterHistogram')
                     ->with($this->identicalTo($expected))
                     ->willReturn($this->createMock(\Prometheus\Histogram::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        $histogram = Histogram::create('name')->observe(2.2);
        if ($namespace) {
            $histogram->setNamespace($namespace);
        }
    }

    public function testName()
    {
        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterHistogram')
                     ->with($this->anything(), $this->identicalTo('__NAME__'))
                     ->willReturn($this->createMock(\Prometheus\Histogram::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        Histogram::create('__NAME__')->observe(2.2);
    }

    public static function helpTextDataProvider(): array
    {
        return [
            'value is set' => [
                'text' => 'this is help text',
                'expected' => 'this is help text',
            ],
            'by default, it should use name' => [
                'text' => null,
                'expected' => '__NAME__',
            ],
        ];
    }

    /** @dataProvider helpTextDataProvider */
    public function testHelpText(?string $text, string $expected)
    {
        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterHistogram')
                     ->with($this->anything(), $this->anything(), $this->identicalTo($expected))
                     ->willReturn($this->createMock(\Prometheus\Histogram::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        $histogram = Histogram::create('__NAME__')->observe(2.2);
        if ($text) {
            $histogram->setHelpText($text);
        }
    }

    public static function labelsDataProvider(): array
    {
        return [
            'labels is never set' => [
                [
                    'labels' => null,
                ],
            ],
            'labels is called with empty array' => [
                [
                    'labels' => [],
                ],
            ],
            'labels is called with single element' => [
                [
                    'labels' => ['method' => 'get'],
                ],
            ],
            'labels is called with multiple elements' => [
                [
                    'labels' => ['method' => 'get', 'url' => '/metrics'],
                ],
            ],
        ];
    }

    /** @dataProvider labelsDataProvider */
    public function testLabels(array $data)
    {
        $names = array_keys($data['labels'] ?? []);
        $values = array_values($data['labels'] ?? []);

        $histogramMock = $this->createMock(\Prometheus\Histogram::class);
        $histogramMock->expects($this->once())->method('observe')->with($this->anything(), $this->identicalTo($values));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterHistogram')
                     ->with($this->anything(), $this->anything(), $this->anything(), $this->identicalTo($names))
                     ->willReturn($histogramMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        $histogram = Histogram::create('my_histogram')->observe(2.2);
        if (isset($data['labels'])) {
            $histogram->labels($data['labels']);
        }
    }

    public function testLabelNameAndValueCanBeSetUsingLabelMethod()
    {
        $histogramMock = $this->createMock(\Prometheus\Histogram::class);
        $histogramMock->expects($this->once())->method('observe')
                      ->with($this->anything(), $this->identicalTo([200, '/metrics']));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterHistogram')
                     ->with(
                         $this->anything(),
                         $this->anything(),
                         $this->anything(),
                         $this->identicalTo(['code', 'url'])
                     )
                     ->willReturn($histogramMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Histogram::create('my_histogram')->label('code', 200)->label('url', '/metrics')->observe(2.2);
    }

    public static function bucketDataProvider(): array
    {
        return [
            'bucket is not set' => [
                'buckets' => null,
                'expected' => null,
            ],
            'list of values is set' => [
                'buckets' => [1, 2, 3, 4],
                'expected' => [1, 2, 3, 4],
            ],
        ];
    }

    /** @dataProvider bucketDataProvider */
    public function testBuckets(?array $buckets, ?array $expected)
    {
        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterHistogram')
                     ->with(
                         $this->anything(),
                         $this->anything(),
                         $this->anything(),
                         $this->anything(),
                         $this->identicalTo($expected)
                     )
                     ->willReturn($this->createMock(\Prometheus\Histogram::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        $histogram = Histogram::create('my_histogram')->observe(2.2);
        if (isset($buckets)) {
            $histogram->buckets($buckets);
        }
    }

    public static function observeMethodDataProvider(): array
    {
        /**
         * Histogram::observe expects float
         */
        return [
            'called with parameter 0' => [
                [
                    'value' => 0,
                    'expected' => 0.0,
                ],
            ],
            'called with parameter 10.65' => [
                [
                    'value' => 10.65,
                    'expected' => 10.65,
                ],
            ],
            'called with parameter 1065' => [
                [
                    'value' => 1065,
                    'expected' => 1065.0,
                ],
            ],
        ];
    }

    /** @dataProvider observeMethodDataProvider */
    public function testObserveMethod(array $data)
    {
        $histogramMock = $this->createMock(\Prometheus\Histogram::class);
        $histogramMock->expects($this->once())->method('observe')->with($this->identicalTo($data['expected']));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterHistogram')
                     ->willReturn($histogramMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Histogram::create('my_histogram')->observe($data['value']);
    }

    public function testHistogramExpectsObservationMethodToBeCalledExplicitly()
    {
        $this->expectException(PrometheusException::class);
        Histogram::create('my_histogram');
    }

    public function testDataIsSavedAutomatically()
    {
        $histogramMock = $this->createMock(\Prometheus\Histogram::class);
        $histogramMock->expects($this->once())->method('observe');

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterHistogram')
                     ->willReturn($histogramMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Histogram::create('name')->observe(2.2);
    }
}
