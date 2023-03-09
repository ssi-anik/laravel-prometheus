<?php

namespace Anik\Laravel\Prometheus;

use Anik\Laravel\Prometheus\Exceptions\PrometheusException;
use Illuminate\Contracts\Foundation\Application;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;

/**
 * @mixin \Anik\Laravel\Prometheus\Metric
 */
class PrometheusManager
{
    protected Application $app;

    protected array $adapters = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function fromConfig(string $key, $default = null)
    {
        return $this->app['config']->get('prometheus.'.$key)
            ?? $this->app['config']->get($key)
            ?? $default;
    }

    protected function getDefaultStorage(): string
    {
        return $this->fromConfig('storage', 'redis');
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Anik\Laravel\Prometheus\Exceptions\PrometheusException
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function metric(?string $storage = null): Metric
    {
        return new Metric($this->adapter($storage), $this->fromConfig('namespace', ''));
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Anik\Laravel\Prometheus\Exceptions\PrometheusException
     */
    public function adapter(?string $storage = null): Adapter
    {
        if (is_null($storage = $storage ?? $this->getDefaultStorage())) {
            throw new PrometheusException('Invalid storage [null].');
        }

        if (isset($this->adapters[$storage])) {
            return $this->adapters[$storage];
        }

        $method = 'create'.Str::studly($storage).'Adapter';
        if (method_exists($this, $method)) {
            return $this->adapters[$storage] = call_user_func([$this, $method]);
        }

        $adapter = $this->app->get($storage);

        if (!$adapter instanceof Adapter) {
            throw new PrometheusException(sprintf('%s is not an instance of %s', $storage, Adapter::class));
        }

        return $this->adapters[$storage] = $adapter;
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function createRedisAdapter(): Adapter
    {
        $options = $this->fromConfig('options.redis', []);
        if (isset($options['prefix'])) {
            Redis::setPrefix($options['prefix']);
            unset($options['prefix']);
        }

        return $this->app->make(Redis::class, [
            'options' => $options,
        ]);
    }

    /**
     * @throws \Prometheus\Exception\StorageException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function createApcAdapter(): Adapter
    {
        return $this->app->make(APC::class, [...$this->fromConfig('options.apc', [])]);
    }

    /**
     * @throws \Prometheus\Exception\StorageException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function createApcngAdapter(): Adapter
    {
        return $this->app->make(APCng::class, [...$this->fromConfig('options.apcng', [])]);
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function createMemoryAdapter(): Adapter
    {
        return $this->createInMemoryAdapter();
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function createInMemoryAdapter(): Adapter
    {
        return $this->app->make(InMemory::class);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Anik\Laravel\Prometheus\Exceptions\PrometheusException
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __call($name, $arguments)
    {
        return $this->metric()->$name(...$arguments);
    }
}
