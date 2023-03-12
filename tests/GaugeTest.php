<?php

namespace Anik\Laravel\Prometheus\Test;

use Anik\Laravel\Prometheus\Collector\Gauge;
use Anik\Laravel\Prometheus\Exceptions\PrometheusException;
use Prometheus\CollectorRegistry;

class GaugeTest extends TestCase
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
                     ->method('getOrRegisterGauge')
                     ->with($this->identicalTo($expected))
                     ->willReturn($this->createMock(\Prometheus\Gauge::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        $gauge = Gauge::create('name')->increment();
        if ($namespace) {
            $gauge->setNamespace($namespace);
        }
    }

    public function testName()
    {
        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterGauge')
                     ->with($this->anything(), $this->identicalTo('__NAME__'))
                     ->willReturn($this->createMock(\Prometheus\Gauge::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        Gauge::create('__NAME__')->increment();
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
                     ->method('getOrRegisterGauge')
                     ->with($this->anything(), $this->anything(), $this->identicalTo($expected))
                     ->willReturn($this->createMock(\Prometheus\Gauge::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        $gauge = Gauge::create('__NAME__')->increment();
        if ($text) {
            $gauge->setHelpText($text);
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

        $gaugeMock = $this->createMock(\Prometheus\Gauge::class);
        $gaugeMock->expects($this->once())->method('incBy')->with($this->anything(), $this->identicalTo($values));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterGauge')
                     ->with($this->anything(), $this->anything(), $this->anything(), $this->identicalTo($names))
                     ->willReturn($gaugeMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        $gauge = Gauge::create('my_gauge')->increment();
        if (isset($data['labels'])) {
            $gauge->labels($data['labels']);
        }
    }

    public function testLabelNameAndValueCanBeSetUsingLabelMethod()
    {
        $gaugeMock = $this->createMock(\Prometheus\Gauge::class);
        $gaugeMock->expects($this->once())->method('incBy')
                  ->with($this->anything(), $this->identicalTo([200, '/metrics']));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterGauge')
                     ->with(
                         $this->anything(),
                         $this->anything(),
                         $this->anything(),
                         $this->identicalTo(['code', 'url'])
                     )
                     ->willReturn($gaugeMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Gauge::create('my_gauge')->label('code', 200)->label('url', '/metrics')->increment();
    }

    public static function incrementMethodDataProvider(): array
    {
        return [
            'called without any parameter' => [
                [
                    'value' => null,
                    'expected' => 1,
                ],
            ],
            'called with parameter 0' => [
                [
                    'value' => 0,
                    'expected' => 0,
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
                    'expected' => 1065,
                ],
            ],
            'called with negative parameter' => [
                [
                    'value' => -222,
                    'expected' => -222,
                ],
            ],
        ];
    }

    /** @dataProvider incrementMethodDataProvider */
    public function testIncrementMethod(array $data)
    {
        $gaugeMock = $this->createMock(\Prometheus\Gauge::class);
        $gaugeMock->expects($this->once())->method('incBy')->with($this->identicalTo($data['expected']));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterGauge')
                     ->willReturn($gaugeMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        $gauge = Gauge::create('my_gauge');
        if (is_null($data['value'])) {
            $gauge->increment();
        } else {
            $gauge->increment($data['value']);
        }
    }

    public static function decrementMethodDataProvider(): array
    {
        return [
            'called without any parameter' => [
                [
                    'value' => null,
                    'expected' => 1,
                ],
            ],
            'called with parameter 0' => [
                [
                    'value' => 0,
                    'expected' => 0,
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
                    'expected' => 1065,
                ],
            ],
            'called with negative parameter' => [
                [
                    'value' => -222,
                    'expected' => -222,
                ],
            ],
        ];
    }

    /** @dataProvider decrementMethodDataProvider */
    public function testDecrementMethod(array $data)
    {
        $gaugeMock = $this->createMock(\Prometheus\Gauge::class);
        $gaugeMock->expects($this->once())->method('decBy')->with($this->identicalTo($data['expected']));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterGauge')
                     ->willReturn($gaugeMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        $gauge = Gauge::create('my_gauge');
        if (is_null($data['value'])) {
            $gauge->decrement();
        } else {
            $gauge->decrement($data['value']);
        }
    }

    public static function setMethodDataProvider(): array
    {
        /**
         * Gauge::set expects float
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
            'called with negative parameter' => [
                [
                    'value' => -222,
                    'expected' => -222.0,
                ],
            ],
        ];
    }

    /** @dataProvider setMethodDataProvider */
    public function testSetMethod(array $data)
    {
        $gaugeMock = $this->createMock(\Prometheus\Gauge::class);
        $gaugeMock->expects($this->once())->method('set')->with($this->identicalTo($data['expected']));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterGauge')
                     ->willReturn($gaugeMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Gauge::create('my_gauge')->set($data['value']);
    }

    public function testGaugeExpectsAnOperationMethodToBeCalledExplicitly()
    {
        $this->expectException(PrometheusException::class);
        Gauge::create('my_gauge');
    }

    public function testDataIsSavedAutomatically()
    {
        $gaugeMock = $this->createMock(\Prometheus\Gauge::class);
        $gaugeMock->expects($this->once())->method('incBy');

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterGauge')
                     ->willReturn($gaugeMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Gauge::create('name')->increment();
    }
}
