<?php

namespace Anik\Laravel\Prometheus;

use Anik\Laravel\Prometheus\Exceptions\PrometheusException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Str;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class PrometheusManager
{
    protected Container $app;

    protected array $adapters = [];

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * @throws BindingResolutionException
     */
    public function createRedisAdapter(array $config): Adapter
    {
        if (isset($config['prefix'])) {
            Redis::setPrefix($config['prefix']);
            unset($config['prefix']);
        }

        return $this->app->make(Redis::class, [
            'options' => $config,
        ]);
    }

    /**
     * @throws BindingResolutionException
     */
    public function createApcAdapter(array $config): Adapter
    {
        return $this->app->make(APC::class, [
            'prometheusPrefix' => $config['prometheusPrefix'] ?? '',
        ]);
    }

    /**
     * @throws BindingResolutionException
     */
    public function createApcngAdapter(array $config): Adapter
    {
        return $this->app->make(APCng::class, [
            'prometheusPrefix' => $config['prometheusPrefix'] ?? '',
        ]);
    }

    /**
     * @throws BindingResolutionException
     */
    public function createMemoryAdapter(): Adapter
    {
        return $this->createInMemoryAdapter();
    }

    /**
     * @throws BindingResolutionException
     */
    public function createInMemoryAdapter(): Adapter
    {
        return $this->app->make(InMemory::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function collectorRegistry(Adapter $adapter, bool $defaultMetrics = false): CollectorRegistry
    {
        return $this->app->make(CollectorRegistry::class, [
            'storageAdapter' => $adapter,
            'registerDefaultMetrics' => $defaultMetrics,
        ]);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws PrometheusException
     * @throws NotFoundExceptionInterface
     */
    public function metric(?string $storage = null, ?string $namespace = null): Metric
    {
        return new Metric(
            $this->collectorRegistry($this->adapter($storage)),
            $namespace ?? $this->fromConfig('namespace', '')
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws PrometheusException
     * @throws BindingResolutionException
     */
    public function samples(?string $storage = null, bool $defaultMetrics = false): Samples
    {
        return new Samples($this->collectorRegistry($this->adapter($storage), $defaultMetrics));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws PrometheusException
     */
    public function adapter(?string $storage = null): Adapter
    {
        $storage = $storage ?? $this->getDefaultStorage();

        if (isset($this->adapters[$storage])) {
            return $this->adapters[$storage];
        }

        $config = $this->fromConfig('options.' . $storage);
        if (empty($config)) {
            throw new PrometheusException('Storage configuration is not defined.');
        }

        $driver = $config['driver'] ?? null;
        unset($config['driver']);

        if (empty($driver)) {
            throw new PrometheusException(sprintf('Driver missing for "%s"', $storage));
        }

        $method = 'create' . Str::studly($driver) . 'Adapter';
        if (method_exists($this, $method)) {
            return $this->adapters[$storage] = call_user_func([$this, $method], $config);
        }

        $adapter = $this->app->get($driver);

        if (!$adapter instanceof Adapter) {
            throw new PrometheusException(sprintf('%s is not an instance of %s', $driver, Adapter::class));
        }

        return $this->adapters[$storage] = $adapter;
    }

    protected function fromConfig(string $key, $default = null)
    {
        return $this->app['config']->get('prometheus.' . $key)
            ?? $this->app['config']->get($key)
            ?? $default;
    }

    protected function getDefaultStorage(): string
    {
        return $this->fromConfig('storage', 'redis');
    }
}
