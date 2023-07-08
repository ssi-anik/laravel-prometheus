<?php

namespace Anik\Laravel\Prometheus\Test\Laravel;

use Anik\Laravel\Prometheus\Collector\Counter;
use Anik\Laravel\Prometheus\Collector\Histogram;
use Anik\Laravel\Prometheus\Extractors\HttpClient;
use Anik\Laravel\Prometheus\Listeners\ResponseReceivedListener;
use Anik\Laravel\Prometheus\Metric;
use Anik\Laravel\Prometheus\Middlewares\PrometheusMiddleware;
use Anik\Laravel\Prometheus\PrometheusManager;
use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Prometheus\CollectorRegistry;

class HttpRequestTest extends TestCase
{
    use InteractsWithStorage {
        setUp as setUpFromTrait;
    }

    public static function toggleHttpMetricTypesDataProvider(): array
    {
        return [
            'both counter & histogram is enabled by default' => [
                [
                    'expects' => [
                        'getOrRegisterCounter' => 1,
                        'getOrRegisterHistogram' => 1,
                    ],
                ],
            ],
            'only counter is disabled' => [
                [
                    'config' => ['prometheus.http.counter.enabled' => false,],
                    'expects' => [
                        'getOrRegisterCounter' => 0,
                        'getOrRegisterHistogram' => 1,
                    ],
                ],
            ],
            'only histogram is disabled' => [
                [
                    'config' => ['prometheus.http.histogram.enabled' => false,],
                    'expects' => [
                        'getOrRegisterCounter' => 1,
                        'getOrRegisterHistogram' => 0,
                    ],
                ],
            ],
            'both counter & histogram is disabled' => [
                [
                    'config' => [
                        'prometheus.http.counter.enabled' => false,
                        'prometheus.http.histogram.enabled' => false,
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 0,
                        'getOrRegisterHistogram' => 0,
                    ],
                ],
            ],
        ];
    }

    public static function ignoreConfigDataProvider(): array
    {
        return [
            'nothing is added to ignore config' => [
                [
                    'requests' => [
                        ['GET', 'https://example.com/200'],
                        ['POST', 'https://example.com/200'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 2,
                        'getOrRegisterHistogram' => 2,
                    ],
                ],
            ],
            'host with empty value is to config' => [
                [
                    'config' => [
                        'prometheus.http.ignore' => ['example.com' => []],
                    ],
                    'requests' => [
                        ['GET', 'https://example.com/200'],
                        ['POST', 'https://example.com/200'],
                        ['GET', 'https://example.org/200'],
                        ['POST', 'https://example.org/200'],
                        ['DELETE', 'https://example.org/500'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 3,
                        'getOrRegisterHistogram' => 3,
                    ],
                ],
            ],
            'host with only paths in indexed array' => [
                [
                    'config' => [
                        'prometheus.http.ignore' => [
                            'example.com' => [],
                            'example.org' => ['/200', '/500']
                        ],
                    ],
                    'requests' => [
                        ['GET', 'https://example.com/200'],
                        ['POST', 'https://example.com/200'],
                        ['GET', 'https://example.org/200'],
                        ['POST', 'https://example.org/400'],
                        ['DELETE', 'https://example.org/500'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 1,
                        'getOrRegisterHistogram' => 1,
                    ],
                ],
            ],
            'host with only paths in associative array' => [
                [
                    'config' => [
                        'prometheus.http.ignore' => [
                            'example.com' => [
                                '/200' => ['POST', 'GET'],
                            ],
                            'example.org' => [
                                '/200' => [],
                                '/500' => '*',
                                '/400' => 'DELETE',
                            ]
                        ],
                    ],
                    'requests' => [
                        ['GET', 'https://example.com/200'],
                        ['POST', 'https://example.com/200'],
                        ['GET', 'https://example.org/200'],
                        ['GET', 'https://example.com/extra'],
                        ['POST', 'https://example.org/400'],
                        ['DELETE', 'https://example.org/500'],
                        ['PATCH', 'https://example.org/500'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 2,
                        'getOrRegisterHistogram' => 2,
                    ],
                ],
            ],
        ];
    }

    public static function histogramBucketDataProvider(): array
    {
        return [
            'is set to some values' => [
                [1, 2, 3, 4, 5],
            ],
            'is set to an empty array' => [
                [],
            ],
            'is set to null' => [
                null,
            ],
        ];
    }

    public function disableHttpMetric($app)
    {
        $app['config']->set(['prometheus.http.enabled' => false]);
    }

    public function testHttpMetricIsEnabledByDefault()
    {
        $this->assertListenerIsAttachedToEvent(ResponseReceived::class, ResponseReceivedListener::class);
    }

    /** @define-env disableHttpMetric */
    public function testHttpMetricCanBeDisabledUsingConfig()
    {
        $this->assertListenerIsNotAttachedToEvent(ResponseReceived::class, ResponseReceivedListener::class);
    }

    /** @dataProvider toggleHttpMetricTypesDataProvider */
    public function testHttpMetricTypesCanBeToggledFromConfig(array $rules)
    {
        foreach ($rules['config'] ?? [] as $key => $value) {
            config([$key => $value]);
        }

        $registryMock = $this->createMock(CollectorRegistry::class);
        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        foreach ($rules['expects'] ?? [] as $method => $times) {
            $registryMock->expects($this->exactly($times))->method($method);
        }

        Http::get('https://example.com/200');
    }

    /** @dataProvider ignoreConfigDataProvider */
    public function testHttpMetricWillBeSkippedIfMatchesDataInIgnoreArray(array $rules)
    {
        foreach ($rules['config'] ?? [] as $key => $value) {
            config([$key => $value]);
        }

        $registryMock = $this->createMock(CollectorRegistry::class);
        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        foreach ($rules['expects'] ?? [] as $method => $times) {
            $registryMock->expects($this->exactly($times))->method($method);
        }

        foreach ($rules['requests'] ?? [] as $request) {
            $verb = strtoupper($request[0]);
            $uri = $request[1];
            forward_static_call_array([Http::class, $verb], [$uri]);
        }
    }

    public function testWhenSavingCounterItUsesNameFromConfig()
    {
        config(['prometheus.http.counter.name' => $counterName = '_counter_name_for_http']);
        config(['prometheus.http.histogram.enabled' => false]);

        $metric = $this->createMock(Metric::class);
        $metric->method('counter')
               ->with($this->identicalTo($counterName))
               ->willReturn(tap($this->createMock(Counter::class), function ($counter) {
                   // Have to do as "static" return type is not supported in PHP <8.0, and it returns self
                   $counter->method('labels')->willReturn($counter);
               }));

        $manager = $this->createMock(PrometheusManager::class);
        $manager->method('metric')->willReturn($metric);

        $this->app->bind(PrometheusManager::class, fn() => $manager);
        Http::get('https://example.com/200');
    }

    public function testWhenSavingHistogramItUsesNameFromConfig()
    {
        config(['prometheus.http.histogram.name' => $histogramName = '_histogram_name']);
        config(['prometheus.http.counter.enabled' => false]);

        $metric = $this->createMock(Metric::class);
        $metric->method('histogram')
               ->with($this->identicalTo($histogramName))
               ->willReturn(tap($this->createMock(Histogram::class), function ($histogram) {
                   // has to be done as "static" return type is not supported in PHP <8.0, and it returns self
                   $histogram->method('labels')->willReturn($histogram);
               }));

        $manager = $this->createMock(PrometheusManager::class);
        $manager->method('metric')->willReturn($metric);

        $this->app->bind(PrometheusManager::class, fn() => $manager);
        Http::get('https://example.com/200');
    }

    /** @dataProvider histogramBucketDataProvider */
    public function testWhenSavingHistogramItUsesBucketFromConfig($buckets)
    {
        config(['prometheus.http.histogram.buckets' => $buckets]);
        config(['prometheus.http.counter.enabled' => false]);

        $histogram = $this->createMock(Histogram::class);
        $histogram->method($this->anything())->willReturn($histogram);
        $histogram->expects(!empty($buckets) ? $this->once() : $this->never())
                  ->method('buckets')
                  ->with($this->callback(fn($arg) => $arg === $buckets))
                  ->willReturn($histogram);

        $metric = $this->createMock(Metric::class);
        $metric->method('histogram')->willReturn($histogram);

        $manager = $this->createMock(PrometheusManager::class);
        $manager->method('metric')->willReturn($metric);

        $this->app->bind(PrometheusManager::class, fn() => $manager);
        Http::get('https://example.com/200');
    }

    public function testExtractorCanBeSetFromConfig()
    {
        config(['prometheus.http.extractor' => '_extractor.http']);

        $request = $this->createMock(HttpClient::class);
        $request->expects($this->once())->method('toArray');
        $this->app->bind('_extractor.http', fn() => $request);

        Http::get('https://example.com/200');
    }

    public function testLabelKeysConsiderLabelsKeysFromConfig()
    {
        config([
            'prometheus.http.labels' => $labels = [
                'scheme' => '_scheme',
                'host' => '_host',
                'path' => '_path',
                'method' => '_method',
                'status' => '_status',
            ],
        ]);

        $metric = $this->createMock(Metric::class);

        $metric->expects($this->once())
               ->method('counter')
               ->willReturn(tap($this->createMock(Counter::class), function ($counter) use ($labels) {
                   $counter->method('labels')
                           ->with($this->callback(function ($args) use ($labels) {
                               $expectedKeys = array_values($labels);

                               return $expectedKeys == array_keys($args);
                           }))
                           ->willReturn($counter);
                   $counter->method('increment')->willReturn($counter);
               }));

        $metric->expects($this->once())
               ->method('histogram')
               ->willReturn(tap($this->createMock(Histogram::class), function ($histogram) use ($labels) {
                   $histogram->method('labels')
                             ->with($this->callback(function ($args) use ($labels) {
                                 $expectedKeys = array_values($labels);

                                 return $expectedKeys == array_keys($args);
                             }))
                             ->willReturn($histogram);
                   $histogram->method('observe')->willReturn($histogram);
                   $histogram->method('buckets')->willReturn($histogram);
               }));

        $manager = $this->createMock(PrometheusManager::class);
        $manager->method('metric')->willReturn($metric);

        $this->app->bind(PrometheusManager::class, fn() => $manager);
        Http::get('https://example.com/200');
    }

    public function testLabelKeysAreSetIfKeyIsPresentInConfig()
    {
        config([
            'prometheus.http.labels' => $labels = [
                'host' => 'host_',
                'method' => 'method_',
            ],
        ]);

        $metric = $this->createMock(Metric::class);

        $metric->expects($this->once())
               ->method('counter')
               ->willReturn(tap($this->createMock(Counter::class), function ($counter) use ($labels) {
                   $counter->method('labels')
                           ->with($this->callback(function ($args) use ($labels) {
                               $expectedKeys = array_values($labels);

                               return $expectedKeys == array_keys($args);
                           }))
                           ->willReturn($counter);
                   $counter->method($this->anything())->willReturn($counter);
               }));

        $metric->expects($this->once())
               ->method('histogram')
               ->willReturn(tap($this->createMock(Histogram::class), function ($histogram) use ($labels) {
                   $histogram->method('labels')
                             ->with($this->callback(function ($args) use ($labels) {
                                 $expectedKeys = array_values($labels);

                                 return $expectedKeys == array_keys($args);
                             }))
                             ->willReturn($histogram);
                   $histogram->method($this->anything())->willReturn($histogram);
               }));

        $manager = $this->createMock(PrometheusManager::class);
        $manager->method('metric')->willReturn($metric);

        $this->app->bind(PrometheusManager::class, fn() => $manager);
        Http::get('https://example.com/200');
    }

    public function testHttpMetricGetsPushedToStorage()
    {
        $metric = $this->createMock(Metric::class);

        $metric->expects($this->once())
               ->method('counter')
               ->willReturn(tap($this->createMock(Counter::class), function ($counter) {
                   $counter->method($this->anything())->willReturn($counter);
               }));

        $metric->expects($this->once())
               ->method('histogram')
               ->willReturn(tap($this->createMock(Histogram::class), function ($histogram) {
                   $histogram->method($this->anything())->willReturn($histogram);
               }));

        $manager = $this->createMock(PrometheusManager::class);
        $manager->method('metric')->willReturn($metric);

        $this->app->bind(PrometheusManager::class, fn() => $manager);
        Http::get('https://example.com/200');
    }

    public function testHttpMetricsCanBeSaveAfterResponseIsSent()
    {
        $terminatingCallbacksCount = function () {
            return count(Closure::bind(fn() => $this->terminatingCallbacks, $this->app)->call($this->app));
        };

        config(['prometheus.http.after_response' => true]);

        $metric = $this->createMock(Metric::class);

        $metric->method('counter')
               ->willReturn(tap($this->createMock(Counter::class), function ($counter) {
                   $counter->method($this->anything())->willReturn($counter);
               }));

        $metric->method('histogram')
               ->willReturn(tap($this->createMock(Histogram::class), function ($histogram) {
                   $histogram->method($this->anything())->willReturn($histogram);
               }));

        $manager = $this->createMock(PrometheusManager::class);
        $manager->method('metric')->willReturn($metric);

        $this->app->bind(PrometheusManager::class, fn() => $manager);
        $terminatingCallbacksBefore = value($terminatingCallbacksCount);
        Http::get('https://example.com/200');
        $terminatingCallbacksAfter = value($terminatingCallbacksCount);
        $this->assertSame($terminatingCallbacksBefore + 1, $terminatingCallbacksAfter);
    }

    protected function setUp(): void
    {
        $this->setUpFromTrait();
        if (version_compare(Application::VERSION, '7.0.0', '<')) {
            $this->markTestSkipped('all tests are marked skipped because of Laravel Version < 7.0.0');
            return;
        }

        Http::fake([
            'example.com/200' => Http::response(['foo' => 'bar']),
            'example.com/400' => Http::response(['foo' => 'bar'], 400),
            'example.com/500' => Http::response(['foo' => 'bar'], 500),
            'example.com/extra' => Http::response(['foo' => 'bar'], 202),

            'example.org/200' => Http::response(['foo' => 'bar']),
            'example.org/400' => Http::response(['foo' => 'bar'], 400),
            'example.org/500' => Http::response(['foo' => 'bar'], 500),
            'example.org/extra' => Http::response(['foo' => 'bar'], 202),
        ]);
    }
}
