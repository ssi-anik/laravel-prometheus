<?php

namespace Anik\Laravel\Prometheus\Test\Laravel;

use Anik\Laravel\Prometheus\Collector\Summary;
use Anik\Laravel\Prometheus\Exceptions\PrometheusException;
use Prometheus\CollectorRegistry;

class SummaryTest extends TestCase
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
                     ->method('getOrRegisterSummary')
                     ->with($this->identicalTo($expected))
                     ->willReturn($this->createMock(\Prometheus\Summary::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        $summary = Summary::create('name')->observe(2.2);
        if ($namespace) {
            $summary->setNamespace($namespace);
        }
    }

    public function testName()
    {
        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterSummary')
                     ->with($this->anything(), $this->identicalTo('__NAME__'))
                     ->willReturn($this->createMock(\Prometheus\Summary::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        Summary::create('__NAME__')->observe(2.2);
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
                     ->method('getOrRegisterSummary')
                     ->with($this->anything(), $this->anything(), $this->identicalTo($expected))
                     ->willReturn($this->createMock(\Prometheus\Summary::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        $summary = Summary::create('__NAME__')->observe(2.2);
        if ($text) {
            $summary->setHelpText($text);
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

        $summaryMock = $this->createMock(\Prometheus\Summary::class);
        $summaryMock->expects($this->once())->method('observe')->with($this->anything(), $this->identicalTo($values));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterSummary')
                     ->with($this->anything(), $this->anything(), $this->anything(), $this->identicalTo($names))
                     ->willReturn($summaryMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        $summary = Summary::create('my_summary')->observe(2.2);
        if (isset($data['labels'])) {
            $summary->labels($data['labels']);
        }
    }

    public function testLabelNameAndValueCanBeSetUsingLabelMethod()
    {
        $summaryMock = $this->createMock(\Prometheus\Summary::class);
        $summaryMock->expects($this->once())->method('observe')
                    ->with($this->anything(), $this->identicalTo([200, '/metrics']));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterSummary')
                     ->with(
                         $this->anything(),
                         $this->anything(),
                         $this->anything(),
                         $this->identicalTo(['code', 'url'])
                     )
                     ->willReturn($summaryMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Summary::create('my_summary')->label('code', 200)->label('url', '/metrics')->observe(2.2);
    }

    public function testMaxAgeSeconds()
    {
        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterSummary')
                     ->with(
                         $this->anything(),
                         $this->anything(),
                         $this->anything(),
                         $this->anything(),
                         $this->identicalTo(300),
                         $this->anything()
                     )
                     ->willReturn($this->createMock(\Prometheus\Summary::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Summary::create('my_summary')->maxAgeSeconds(300)->observe(2.2);
    }

    public static function quantilesDataProvider(): array
    {
        return [
            'quantiles is not set' => [
                'quantiles' => null,
                'expected' => null,
            ],
            'list of values is set' => [
                'quantiles' => [1, 2, 3, 4],
                'expected' => [1, 2, 3, 4],
            ],
        ];
    }

    /** @dataProvider quantilesDataProvider */
    public function testQuantiles(?array $quantiles, ?array $expected)
    {
        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterSummary')
                     ->with(
                         $this->anything(),
                         $this->anything(),
                         $this->anything(),
                         $this->anything(),
                         $this->anything(),
                         $this->identicalTo($expected)
                     )
                     ->willReturn($this->createMock(\Prometheus\Summary::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        $summary = Summary::create('my_summary')->observe(2.2);
        if (isset($quantiles)) {
            $summary->quantiles($quantiles);
        }
    }

    public static function observeMethodDataProvider(): array
    {
        /**
         * Summary::observe expects float
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
        $summaryMock = $this->createMock(\Prometheus\Summary::class);
        $summaryMock->expects($this->once())->method('observe')->with($this->identicalTo($data['expected']));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterSummary')
                     ->willReturn($summaryMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Summary::create('my_summary')->observe($data['value']);
    }

    public function testSummaryExpectsObservationMethodToBeCalledExplicitly()
    {
        $this->expectException(PrometheusException::class);
        Summary::create('my_summary');
    }

    public function testDataIsSavedAutomatically()
    {
        $summaryMock = $this->createMock(\Prometheus\Summary::class);
        $summaryMock->expects($this->once())->method('observe');

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterSummary')
                     ->willReturn($summaryMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Summary::create('name')->observe(2.2);
    }
}
