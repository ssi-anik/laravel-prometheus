<?php

namespace Anik\Laravel\Prometheus\Test\Laravel;

use Anik\Laravel\Prometheus\Exceptions\PrometheusException;
use Anik\Laravel\Prometheus\PrometheusManager;
use Closure;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;

class PrometheusManagerTest extends TestCase
{
    public function testCanCreateRedisAdapter()
    {
        $this->assertInstanceOf(Redis::class, $this->app->make(PrometheusManager::class)->createRedisAdapter());
    }

    public function testCreateRedisAdapterConsidersConfigOptions()
    {
        $optionsToPass = [
            'host' => 'redis-host',
            'port' => 9292,
            'password' => 'password',
            'username' => 'username',
            'database' => 2,
            'timeout' => 10,
            'read_timeout' => 20,
            'persistent_connections' => true,
        ];

        config(['prometheus.options.redis' => $optionsToPass + ['prefix' => $prefix = 'prefix_is_set_']]);

        $adapter = $this->app->make(PrometheusManager::class)->createRedisAdapter();
        $optionsInClass = Closure::fromCallable(function () {
            return $this->options;
        })->bindTo($adapter)->call($adapter);

        $this->assertEquals($optionsInClass, $optionsToPass);

        $prefixInClass = Closure::fromCallable(function () {
            return self::$prefix;
        })->bindTo($adapter)->call($adapter);

        $this->assertEquals($prefixInClass, $prefix);
    }

    public function testCanCreateApcAdapter()
    {
        $this->assertInstanceOf(APC::class, $this->app->make(PrometheusManager::class)->createApcAdapter());
    }

    public function testCreateApcAdapterConsidersConfigOptions()
    {
        config(['prometheus.options.apc' => ['prometheusPrefix' => $prefix = 'prom_prefix']]);

        $adapter = $this->app->make(PrometheusManager::class)->createApcAdapter();
        $prefixInClass = Closure::fromCallable(function () {
            return $this->prometheusPrefix;
        })->bindTo($adapter)->call($adapter);

        $this->assertEquals($prefixInClass, $prefix);
    }

    public function testCanCreateApcngAdapter()
    {
        $this->assertInstanceOf(APCng::class, $this->app->make(PrometheusManager::class)->createApcngAdapter());
    }

    public function testCreateApcngAdapterConsidersConfigOptions()
    {
        config(['prometheus.options.apcng' => ['prometheusPrefix' => $prefix = 'prom_prefix']]);

        $adapter = $this->app->make(PrometheusManager::class)->createApcngAdapter();
        $prefixInClass = Closure::fromCallable(function () {
            return $this->prometheusPrefix;
        })->bindTo($adapter)->call($adapter);

        $this->assertEquals($prefixInClass, $prefix);
    }

    public function testCanCreateInMemoryAdapter()
    {
        $this->assertInstanceOf(InMemory::class, $this->app->make(PrometheusManager::class)->createMemoryAdapter());
        $this->assertInstanceOf(InMemory::class, $this->app->make(PrometheusManager::class)->createInMemoryAdapter());
    }

    public static function adapterMethodStorageConfigDataProvider(): array
    {
        return [
            'null as storage should use redis by default' => [
                'storage' => null,
                'expected' => Redis::class,
            ],
            'redis as storage' => [
                'storage' => 'redis',
                'expected' => Redis::class,
            ],
            'apc as storage' => [
                'storage' => 'apc',
                'expected' => APC::class,
            ],
            'apcng as storage' => [
                'storage' => 'apcng',
                'expected' => APCng::class,
            ],
            'memory as storage' => [
                'storage' => 'memory',
                'expected' => InMemory::class,
            ],
            'memory as storage' => [
                'storage' => 'in-memory',
                'expected' => InMemory::class,
            ],
        ];
    }

    /** @dataProvider adapterMethodStorageConfigDataProvider */
    public function testAdapterMethodConsidersStorageConfigToCreateAdapter(?string $storage, string $expected)
    {
        config(['prometheus.storage' => $storage]);

        $this->assertInstanceOf($expected, $this->app->make(PrometheusManager::class)->adapter());
    }

    public function testAdapterMethodUsesServiceContainerToGetAdapter()
    {
        $this->app->bind('fake_adapter', function () {
            return $this->app->make(APC::class);
        });
        config(['prometheus.storage' => 'fake_adapter']);

        $this->assertInstanceOf(APC::class, $this->app->make(PrometheusManager::class)->adapter());
    }

    public function testAdapterMethodRaiseExceptionIfServiceContainerResolvedClassIsNotAnAdapter()
    {
        config(['prometheus.storage' => 'prometheus']);

        $this->expectException(PrometheusException::class);
        $this->expectExceptionMessage(sprintf('prometheus is not an instance of %s', Adapter::class));
        $this->app->make(PrometheusManager::class)->adapter();
    }

    public function testAdapterMethodReturnsTheResolvedAdapterForTheStorage()
    {
        config(['prometheus.storage' => 'memory']);
        $manager = $this->app->make(PrometheusManager::class);
        $manager->adapter();

        $exists = Closure::fromCallable(function () {
            return array_key_exists('memory', $this->adapters);
        })->bindTo($manager)->call($manager);

        $this->assertTrue($exists);

        $manager->adapter();
    }

    /** @dataProvider adapterMethodStorageConfigDataProvider */
    public function testMetricMethodUsesStorageParameter(?string $storage, string $expected)
    {
        $metric = $this->app->make(PrometheusManager::class)->metric($storage);

        $this->assertInstanceOf($expected, $metric->getAdapter());
    }

    public function testMetricMethodUsesNamespaceFromConfig()
    {
        config(['prometheus.namespace' => $namespace = '__NAMESPACE__']);
        $metric = $this->app->make(PrometheusManager::class)->metric();

        $this->assertEquals($namespace, $metric->getNamespace());
    }

    public function testCallMagicMethodPassesToMetricInstance()
    {
        config(['prometheus.storage' => 'memory']);
        config(['prometheus.namespace' => $namespace = '__NAMESPACE__']);

        $manager = $this->app->make(PrometheusManager::class);

        $this->assertInstanceOf(InMemory::class, $manager->getAdapter());
        $this->assertEquals($namespace, $manager->getNamespace());
    }
}
