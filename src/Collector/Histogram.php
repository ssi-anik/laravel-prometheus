<?php

namespace Anik\Laravel\Prometheus\Collector;

use Anik\Laravel\Prometheus\Exceptions\PrometheusException;

final class Histogram extends Collector
{
    /** @var null|int|float */
    protected $value = null;
    protected ?array $buckets = null;

    public function buckets(array $buckets): self
    {
        $this->buckets = $buckets;

        return $this;
    }

    public function observe(float $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @throws \Prometheus\Exception\MetricsRegistrationException
     * @throws \Anik\Laravel\Prometheus\Exceptions\PrometheusException
     */
    protected function store(): bool
    {
        if (is_null($this->value)) {
            throw new PrometheusException('Did you forget to set value through "observe" method?');
        }

        $keys = array_keys($this->labels);
        $values = array_values($this->labels);

        $this->getCollectorRegistry()
             ->getOrRegisterHistogram(
                 $this->getNamespace(),
                 $this->getName(),
                 $this->getHelpText(),
                 $keys,
                 $this->buckets,
             )
             ->observe($this->value, $values);

        return true;
    }
}
