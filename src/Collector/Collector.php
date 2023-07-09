<?php

namespace Anik\Laravel\Prometheus\Collector;

use Anik\Laravel\Prometheus\Exceptions\PrometheusException;
use Prometheus\CollectorRegistry;

abstract class Collector
{
    protected string $namespace = '';
    protected string $name;
    protected ?string $helpText = null;
    protected array $labels = [];
    protected bool $isSaved = false;
    protected ?CollectorRegistry $registry = null;

    public function __construct(string $name, ?CollectorRegistry $registry = null)
    {
        $this->name = $name;
        $this->registry = $registry;
    }

    /** @return static */
    public static function create(string $name, ?CollectorRegistry $registry = null)
    {
        return new static($name, $registry);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /** @return static */
    public function setNamespace(string $namespace)
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHelpText(): string
    {
        return $this->helpText ?? $this->name;
    }

    /** @return static */
    public function setHelpText(string $helpText)
    {
        $this->helpText = $helpText;

        return $this;
    }

    public function getCollectorRegistry(): ?CollectorRegistry
    {
        return $this->registry;
    }

    /** @return static */
    public function labels(array $labels)
    {
        $this->labels = $labels;

        return $this;
    }

    /** @return static */
    public function label(string $name, $value)
    {
        $this->labels[$name] = $value;

        return $this;
    }

    /** @return static */
    public function skip()
    {
        $this->isSaved = true;

        return $this;
    }

    public function __destruct()
    {
        $this->save();
    }

    /**
     * @throws \Anik\Laravel\Prometheus\Exceptions\PrometheusException
     */
    public function save(): void
    {
        if ($this->isSaved) {
            return;
        }

        if (is_null($this->getCollectorRegistry())) {
            throw new PrometheusException(
                sprintf('%s::class requires \Prometheus\Storage\Adapter instance', get_class($this))
            );
        }

        $this->store();

        $this->isSaved = true;
    }

    /** @return static */
    public function setCollectoryRegistry(CollectorRegistry $registry)
    {
        $this->registry = $registry;

        return $this;
    }

    abstract protected function store(): void;
}
