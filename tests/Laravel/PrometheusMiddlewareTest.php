<?php

namespace Anik\Laravel\Prometheus\Test\Laravel;

use Anik\Laravel\Prometheus\Collector\Counter;
use Anik\Laravel\Prometheus\Collector\Histogram;
use Anik\Laravel\Prometheus\Extractors\HttpRequest;
use Anik\Laravel\Prometheus\Metric;
use Anik\Laravel\Prometheus\Middlewares\PrometheusMiddleware;
use Anik\Laravel\Prometheus\PrometheusManager;
use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Prometheus\CollectorRegistry;

class PrometheusMiddlewareTest extends TestCase
{
    use InteractsWithStorage;

    public function disableRequestMetrics($app)
    {
        $app['config']->set(['prometheus.request.enabled' => false]);
    }

    protected function getRouteHandler(): Closure
    {
        return function (Request $request): JsonResponse {
            $code = $request->get('code', 200);

            return response()->json(['success' => $code >= 200 && $code < 400], $code);
        };
    }

    public function addRoute(string $url, string $method = 'GET', $attributes = [])
    {
        $method = strtolower($method);

        if (empty($attributes)) {
            $attributes = $this->getRouteHandler();
        }

        $this->app['router']->$method($url, $attributes);
    }

    protected function startTime()
    {
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
    }

    public function testRequestMetricsIsEnabledByDefault()
    {
        $this->assertTrue($this->app[Kernel::class]->hasMiddleware(PrometheusMiddleware::class));
    }

    /** @define-env disableRequestMetrics */
    public function testRequestMetricsCanBeDisabledUsingConfig()
    {
        $this->assertFalse($this->app[Kernel::class]->hasMiddleware(PrometheusMiddleware::class));
    }

    public function testRequestMiddlewareIsTerminable()
    {
        $this->assertTrue($this->app->bound(PrometheusMiddleware::class));
        $this->assertTrue(method_exists(PrometheusMiddleware::class, 'terminate'));
    }

    public function testRequestMetricIsNeverStoredIfConstantIsNotDefined()
    {
        // Constant is not set, so terminate method will just return without calling metrics save
        $mock = $this->createMock(CollectorRegistry::class);
        $mock->expects($this->never())->method('getOrRegisterCounter');
        $mock->expects($this->never())->method('getOrRegisterHistogram');

        $this->app->bind(CollectorRegistry::class, fn() => $mock);

        $this->addRoute('/homepage');
        $this->get('/homepage')->assertSuccessful();
    }

    public function testMetricsWillBeStoredIfAppStartConstantIsSet()
    {
        if (!defined('APP_START')) {
            define('APP_START', microtime(true));
        }

        $mock = $this->createMock(CollectorRegistry::class);
        $mock->expects($this->once())->method('getOrRegisterCounter');
        $mock->expects($this->once())->method('getOrRegisterHistogram');

        $this->app->bind(CollectorRegistry::class, fn() => $mock);

        $this->addRoute('/homepage');
        $this->get('/homepage')->assertSuccessful();
    }

    public static function toggleRequestMetricTypesDataProvider(): array
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
                    'config' => ['prometheus.request.counter.enabled' => false,],
                    'expects' => [
                        'getOrRegisterCounter' => 0,
                        'getOrRegisterHistogram' => 1,
                    ],
                ],
            ],
            'only histogram is disabled' => [
                [
                    'config' => ['prometheus.request.histogram.enabled' => false,],
                    'expects' => [
                        'getOrRegisterCounter' => 1,
                        'getOrRegisterHistogram' => 0,
                    ],
                ],
            ],
            'both counter & histogram is disabled' => [
                [
                    'config' => [
                        'prometheus.request.counter.enabled' => false,
                        'prometheus.request.histogram.enabled' => false,
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 0,
                        'getOrRegisterHistogram' => 0,
                    ],
                ],
            ],
        ];
    }

    /** @dataProvider toggleRequestMetricTypesDataProvider */
    public function testRequestMetricTypesCanBeToggledFromConfig(array $rules)
    {
        $this->startTime();

        foreach ($rules['config'] ?? [] as $key => $value) {
            config([$key => $value]);
        }

        $registryMock = $this->createMock(CollectorRegistry::class);
        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        foreach ($rules['expects'] ?? [] as $method => $times) {
            $registryMock->expects($this->exactly($times))->method($method);
        }

        $this->addRoute('/homepage');
        $this->get('/homepage')->assertSuccessful();
    }

    public static function ignoreMethodsDataProvider(): array
    {
        return [
            'nothing is added to ignore methods config' => [
                [
                    'routes' => [
                        ['OPTIONS', '/path/to/ignore/1'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 0,
                        'getOrRegisterHistogram' => 0,
                    ],
                ],
            ],
            'GET and OPTIONS HTTP verb is added to config' => [
                [
                    'config' => [
                        'prometheus.request.ignore.methods' => ['OPTIONS', 'GET'],
                    ],
                    'routes' => [
                        ['GET', '/path/to/ignore/1'],
                        ['POST', '/path/to/ignore/1'],
                        ['OPTIONS', '/path/to/ignore/1'],
                        ['OPTIONS', '/path/to/ignore/1'],
                        ['GET', '/path/to/ignore/2'],
                        ['POST', '/path/to/ignore/2'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 2,
                        'getOrRegisterHistogram' => 2,
                    ],
                ],
            ],
            'only POST HTTP verb is added to config replacing OPTIONS' => [
                [
                    'config' => [
                        'prometheus.request.ignore.methods' => ['POST'],
                    ],
                    'routes' => [
                        ['GET', '/path/to/ignore/1'],
                        ['POST', '/path/to/ignore/1'],
                        ['OPTIONS', '/path/to/ignore/1'],
                        ['OPTIONS', '/path/to/ignore/1'],
                        ['GET', '/path/to/ignore/2'],
                        ['POST', '/path/to/ignore/2'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 4,
                        'getOrRegisterHistogram' => 4,
                    ],
                ],
            ],
        ];
    }

    /** @dataProvider ignoreMethodsDataProvider */
    public function testRequestMetricsWillBeSkippedIfHttpMethodsMatchDataInIgnoreArray(array $rules)
    {
        $this->startTime();

        foreach ($rules['config'] ?? [] as $key => $value) {
            config([$key => $value]);
        }

        $registryMock = $this->createMock(CollectorRegistry::class);
        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        foreach ($rules['expects'] ?? [] as $method => $times) {
            $registryMock->expects($this->exactly($times))->method($method);
        }

        foreach ($rules['routes'] ?? [] as $route) {
            $this->addRoute($route[1], $verb = strtoupper($route[0]));
            $this->{$verb}($route[1])->assertSuccessful();
        }
    }

    public static function ignorePathsDataProvider(): array
    {
        return [
            'nothing is added to ignore paths config' => [
                [
                    'routes' => [
                        ['GET', '/path/to/ignore/1'],
                        ['POST', '/path/to/ignore/1'],
                        ['PUT', '/path/to/ignore/1'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 3,
                        'getOrRegisterHistogram' => 3,
                    ],
                ],
            ],
            'paths with empty http verb' => [
                [
                    'config' => [
                        'prometheus.request.ignore.paths' => [
                            'path/to/ignore/1' => '',
                        ],
                    ],
                    'routes' => [
                        ['GET', '/path/to/ignore/1'],
                        ['POST', '/path/to/ignore/1'],
                        ['PUT', '/path/to/ignore/1'],
                        ['GET', '/path/to/ignore/2'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 1,
                        'getOrRegisterHistogram' => 1,
                    ],
                ],
            ],
            'paths with specific http verb' => [
                [
                    'config' => [
                        'prometheus.request.ignore.paths' => [
                            'path/to/ignore/1' => 'GET',
                        ],
                    ],
                    'routes' => [
                        ['GET', '/path/to/ignore/1'],
                        ['POST', '/path/to/ignore/1'],
                        ['PUT', '/path/to/ignore/1'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 2,
                        'getOrRegisterHistogram' => 2,
                    ],
                ],
            ],
            'paths with multiple http verbs' => [
                [
                    'config' => [
                        'prometheus.request.ignore.paths' => [
                            'path/to/ignore/1' => ['GET', 'POST'],
                        ],
                    ],
                    'routes' => [
                        ['GET', '/path/to/ignore/1'],
                        ['POST', '/path/to/ignore/1'],
                        ['PUT', '/path/to/ignore/1'],
                        ['PATCH', '/path/to/ignore/1'],
                        ['DELETE', '/path/to/ignore/1'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 3,
                        'getOrRegisterHistogram' => 3,
                    ],
                ],
            ],
            'path with regex' => [
                [
                    'config' => [
                        'prometheus.request.ignore.paths' => [
                            'path/to/*' => '',
                        ],
                    ],
                    'routes' => [
                        ['GET', '/path/to/ignore/1'],
                        ['POST', '/path/to/ignore/2'],
                        ['PUT', '/path/to/ignore/3'],
                        ['PATCH', '/path/to/ignore/4'],
                        ['DELETE', '/path/to/ignore/5'],
                        ['GET', '/anything-else'],
                        ['POST', '/anything-else'],
                        ['PATCH', '/anything-else'],
                        ['DELETE', '/anything-else'],
                    ],
                    'expects' => [
                        'getOrRegisterCounter' => 4,
                        'getOrRegisterHistogram' => 4,
                    ],
                ],
            ],
        ];
    }

    /** @dataProvider ignorePathsDataProvider */
    public function testRequestMetricsWillBeSkippedIfPathsMatchDataInIgnoreArray(array $rules)
    {
        $this->startTime();

        foreach ($rules['config'] ?? [] as $key => $value) {
            config([$key => $value]);
        }

        $registryMock = $this->createMock(CollectorRegistry::class);
        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        foreach ($rules['expects'] ?? [] as $method => $times) {
            $registryMock->expects($this->exactly($times))->method($method);
        }

        foreach ($rules['routes'] ?? [] as $route) {
            $this->addRoute($route[1], $verb = strtoupper($route[0]));
            $this->{$verb}($route[1])->assertSuccessful();
        }
    }

    public function testWhenSavingCounterItUsesNameFromConfig()
    {
        $this->startTime();
        config(['prometheus.request.counter.name' => $counterName = '_counter_name']);
        config(['prometheus.request.histogram.enabled' => false]);

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
        $this->addRoute('/homepage');
        $this->get('/homepage')->assertSuccessful();
    }

    public function testWhenSavingHistogramItUsesNameFromConfig()
    {
        $this->startTime();
        config(['prometheus.request.histogram.name' => $histogramName = '_histogram_name']);
        config(['prometheus.request.counter.enabled' => false]);

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
        $this->addRoute('/homepage');
        $this->get('/homepage')->assertSuccessful();
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

    /** @dataProvider histogramBucketDataProvider */
    public function testWhenSavingHistogramItUsesBucketFromConfig($buckets)
    {
        $this->startTime();
        config(['prometheus.request.histogram.buckets' => $buckets]);
        config(['prometheus.request.counter.enabled' => false]);

        $histogram = $this->createMock(Histogram::class);
        $histogram->expects(!empty($buckets) ? $this->once() : $this->never())
                  ->method('buckets')
                  ->with($this->callback(fn($arg) => $arg === $buckets))
                  ->willReturn($histogram);
        $histogram->method('labels')->willReturn($histogram);
        $histogram->method('observe')->willReturn($histogram);

        $metric = $this->createMock(Metric::class);
        $metric->method('histogram')->willReturn($histogram);

        $manager = $this->createMock(PrometheusManager::class);
        $manager->method('metric')->willReturn($metric);

        $this->app->bind(PrometheusManager::class, fn() => $manager);
        $this->addRoute('/homepage');
        $this->get('/homepage')->assertSuccessful();
    }

    public function testExtractorCanBeSetFromConfig()
    {
        $this->startTime();
        config(['prometheus.request.extractor' => '_extractor']);

        $request = $this->createMock(HttpRequest::class);
        $request->expects($this->once())->method('toArray');
        $this->app->bind('_extractor', fn() => $request);

        $this->addRoute('/homepage');
        $this->get('/homepage')->assertSuccessful();
    }

    public static function allowEmptyDataProvider(): array
    {
        return [
            'allow empty is disabled by default' => [false],
            'allow empty is set to true' => [true],
        ];
    }

    /** @dataProvider allowEmptyDataProvider */
    public function testMetricsWillBeSkippedIfExtractorReturnEmptyArrayAsData(bool $allowEmpty)
    {
        $this->startTime();
        config(['prometheus.request.extractor' => '_extractor']);
        if ($allowEmpty) {
            config(['prometheus.request.allow_empty' => true]);
        }

        $request = $this->createMock(HttpRequest::class);
        $request->method('toArray')->willReturn([]);
        $this->app->bind('_extractor', fn() => $request);

        $metric = $this->createMock(Metric::class);

        $metric->expects($allowEmpty ? $this->once() : $this->never())
               ->method('counter')
               ->willReturn(tap($this->createMock(Counter::class), function ($counter) {
                   $counter->method($this->anything())->willReturn($counter);
               }));

        $metric->expects($allowEmpty ? $this->once() : $this->never())
               ->method('histogram')
               ->willReturn(tap($this->createMock(Histogram::class), function ($histogram) {
                   $histogram->method($this->anything())->willReturn($histogram);
               }));

        $manager = $this->createMock(PrometheusManager::class);
        $manager->method('metric')->willReturn($metric);

        $this->app->bind(PrometheusManager::class, fn() => $manager);

        $this->addRoute('/homepage');
        $this->get('/homepage')->assertSuccessful();
    }

    public static function metricLabelsDataProvider(): array
    {
        return [
            'uses default labels' => [[]],
            'renaming labels' => [
                [
                    'method' => '_method',
                    'url' => 'path',
                    'status' => 'code',
                ]
            ],
            'skipping few labels' => [
                [
                    'status' => 'code',
                ]
            ],
        ];
    }

    /** @dataProvider metricLabelsDataProvider */
    public function testRequestAndResponseKeysConsiderLabelsKeysFromConfig(array $labels)
    {
        $this->startTime();
        if ($labels) {
            config(['prometheus.request.labels' => $labels,]);
        } else {
            // default labels from prometheus config
            $labels = [
                'method' => 'method',
                'url' => 'url',
                'status' => 'status',
            ];
        }

        $metric = $this->createMock(Metric::class);

        $metric->expects($this->once())
               ->method('counter')
               ->willReturn(tap($this->createMock(Counter::class), function ($counter) use ($labels) {
                   $counter->method('labels')
                           ->with($this->callback(function ($args) use ($labels) {
                               $expectedKeys = array_values($labels);
                               $providedKeys = array_keys($args);

                               asort($expectedKeys);
                               asort($providedKeys);

                               return array_values($expectedKeys) == array_values($providedKeys);
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
                                 $providedKeys = array_keys($args);

                                 asort($expectedKeys);
                                 asort($providedKeys);

                                 return array_values($expectedKeys) == array_values($providedKeys);
                             }))
                             ->willReturn($histogram);
                   $histogram->method('observe')->willReturn($histogram);
                   $histogram->method('buckets')->willReturn($histogram);
               }));

        $manager = $this->createMock(PrometheusManager::class);
        $manager->method('metric')->willReturn($metric);

        $this->app->bind(PrometheusManager::class, fn() => $manager);
        $this->addRoute('/homepage');
        $this->get('/homepage')->assertSuccessful();
    }

    public function testMetricGetsPushedToStorage()
    {
        $this->startTime();

        $metric = $this->createMock(Metric::class);

        $metric->expects($this->once())
               ->method('counter')
               ->willReturn(tap($this->createMock(Counter::class), function ($counter) {
                   $counter->method('labels')->willReturn($counter);
                   $counter->method('increment')->willReturn($counter);
               }));

        $metric->expects($this->once())
               ->method('histogram')
               ->willReturn(tap($this->createMock(Histogram::class), function ($histogram) {
                   $histogram->method('labels')->willReturn($histogram);
                   $histogram->method('observe')->willReturn($histogram);
                   $histogram->method('buckets')->willReturn($histogram);
               }));

        $manager = $this->createMock(PrometheusManager::class);
        $manager->method('metric')->willReturn($metric);

        $this->app->bind(PrometheusManager::class, fn() => $manager);
        $this->addRoute('/homepage');
        $this->get('/homepage')->assertSuccessful();
    }
}
