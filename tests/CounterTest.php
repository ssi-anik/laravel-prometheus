<?php

namespace Anik\Laravel\Prometheus\Test;

use Anik\Laravel\Prometheus\Collector\Counter;
use Prometheus\CollectorRegistry;

class CounterTest extends TestCase
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
                     ->method('getOrRegisterCounter')
                     ->with($this->identicalTo($expected))
                     ->willReturn($this->createMock(\Prometheus\Counter::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        $counter = Counter::create('name');
        if ($namespace) {
            $counter->setNamespace($namespace);
        }
    }

    public function testName()
    {
        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterCounter')
                     ->with($this->anything(), $this->identicalTo('__NAME__'))
                     ->willReturn($this->createMock(\Prometheus\Counter::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        Counter::create('__NAME__');
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
                     ->method('getOrRegisterCounter')
                     ->with($this->anything(), $this->anything(), $this->identicalTo($expected))
                     ->willReturn($this->createMock(\Prometheus\Counter::class));

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);
        $counter = Counter::create('__NAME__');
        if ($text) {
            $counter->setHelpText($text);
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

        $counterMock = $this->createMock(\Prometheus\Counter::class);
        $counterMock->expects($this->once())->method('incBy')->with($this->anything(), $this->identicalTo($values));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterCounter')
                     ->with($this->anything(), $this->anything(), $this->anything(), $this->identicalTo($names))
                     ->willReturn($counterMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        $counter = Counter::create('my_counter');
        if (isset($data['labels'])) {
            $counter->labels($data['labels']);
        }
    }

    public function testLabelNameAndValueCanBeSetUsingLabelMethod()
    {
        $counterMock = $this->createMock(\Prometheus\Counter::class);
        $counterMock->expects($this->once())->method('incBy')
                    ->with($this->anything(), $this->identicalTo([200, '/metrics']));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterCounter')
                     ->with(
                         $this->anything(),
                         $this->anything(),
                         $this->anything(),
                         $this->identicalTo(['code', 'url'])
                     )
                     ->willReturn($counterMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Counter::create('my_counter')->label('code', 200)->label('url', '/metrics');
    }

    public static function incrementValueDataProvider(): array
    {
        return [
            'increment is never called' => [
                [
                    'invoke' => false,
                    'expected' => 1,
                ],
            ],
            'increment is called without any parameter' => [
                [
                    'count' => null,
                    'expected' => 1,
                ],
            ],
            'increment is called count set to 0' => [
                [
                    'count' => 0,
                    'expected' => 0,
                ],
            ],
            'increment is called count set to 10.65' => [
                [
                    'count' => 10.65,
                    'expected' => 10.65,
                ],
            ],
            'increment is called count set to 1065' => [
                [
                    'count' => 1065,
                    'expected' => 1065,
                ],
            ],
        ];
    }

    /** @dataProvider incrementValueDataProvider */
    public function testIncrementMethod(array $data)
    {
        $counterMock = $this->createMock(\Prometheus\Counter::class);
        $counterMock->expects($this->once())->method('incBy')->with($this->identicalTo($data['expected']));

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterCounter')
                     ->willReturn($counterMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        $counter = Counter::create('my_counter');
        if ($data['invoke'] ?? true) {
            if (is_null($data['count'])) {
                $counter->increment();
            } else {
                $counter->increment($data['count']);
            }
        }
    }

    public function testDataIsSavedAutomatically()
    {
        $counterMock = $this->createMock(\Prometheus\Counter::class);
        $counterMock->expects($this->once())->method('incBy');

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->expects($this->once())
                     ->method('getOrRegisterCounter')
                     ->willReturn($counterMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Counter::create('name');
    }
}
