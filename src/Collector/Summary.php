<?php

namespace Anik\Laravel\Prometheus\Collector;

use Anik\Laravel\Prometheus\Exceptions\PrometheusException;

class Summary extends Collector
{
    protected ?float $value = null;
    protected int $maxAgeSeconds = 600;
    protected ?array $quantiles = null;

    public function maxAgeSeconds(int $maxAge): self
    {
        $this->maxAgeSeconds = $maxAge;

        return $this;
    }

    public function quantiles(array $quantiles): self
    {
        $this->quantiles = $quantiles;

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
    public function store(): void
    {
        if (is_null($this->value)) {
            throw new PrometheusException('Did you forget to set value through "observe" method?');
        }

        $keys = array_keys($this->labels);
        $values = array_values($this->labels);

        $this->getCollectorRegistry()
             ->getOrRegisterSummary(
                 $this->getNamespace(),
                 $this->getName(),
                 $this->getHelpText(),
                 $keys,
                 $this->maxAgeSeconds,
                 $this->quantiles,
             )
             ->observe($this->value, $values);
    }
}
