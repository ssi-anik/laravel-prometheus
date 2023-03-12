<?php

namespace Anik\Laravel\Prometheus\Collector;

use Anik\Laravel\Prometheus\PrometheusManager;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;

abstract class Collector
{
    protected string $namespace = '';
    protected string $name;
    protected ?string $helpText = null;
    protected array $labels = [];
    protected bool $isSaved = false;
    protected ?Adapter $adapter = null;
    protected ?string $storage = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /** @return static */
    public static function create(string $name)
    {
        return new static($name);
    }

    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setHelpText(string $helpText): self
    {
        $this->helpText = $helpText;

        return $this;
    }

    public function getHelpText(): string
    {
        return $this->helpText ?? $this->name;
    }

    public function setAdapter(Adapter $adapter): self
    {
        $this->adapter = $adapter;

        return $this;
    }

    public function getAdapter(): Adapter
    {
        return $this->adapter ?? $this->adapter = app(PrometheusManager::class)->adapter($this->storage);
    }

    public function setStorage(string $storage): self
    {
        $this->storage = $storage;

        return $this;
    }

    public function getCollectorRegistry(): CollectorRegistry
    {
        return app()->make(CollectorRegistry::class, [
            'storageAdapter' => $this->getAdapter(),
        ]);
    }

    public function labels(array $labels): self
    {
        $this->labels = $labels;

        return $this;
    }

    public function label(string $name, $value): self
    {
        $this->labels[$name] = $value;

        return $this;
    }

    public function save(): bool
    {
        if ($this->isSaved) {
            return true;
        }

        if (!$this->store()) {
            return false;
        }

        return $this->isSaved = true;
    }

    abstract protected function store(): bool;

    public function __destruct()
    {
        $this->save();
    }
}
