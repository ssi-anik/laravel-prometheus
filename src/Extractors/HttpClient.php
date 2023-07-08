<?php

namespace Anik\Laravel\Prometheus\Extractors;

use GuzzleHttp\TransferStats;
use Illuminate\Contracts\Support\Arrayable;

class HttpClient implements Arrayable
{
    protected TransferStats $stats;
    protected array $labels;
    protected array $modifiers;

    public function __construct(TransferStats $stats, array $labels = [], array $modifiers = [])
    {
        $this->stats = $stats;
        $this->labels = $labels;
        $this->modifiers = $modifiers;
    }

    public function toArray(): array
    {
        $stats = $this->stats;
        $scheme = $stats->getRequest()->getUri()->getScheme();
        $host = $stats->getRequest()->getUri()->getHost();
        $path = $stats->getRequest()->getUri()->getPath();
        $method = $stats->getRequest()->getMethod();
        $status = $stats->getResponse()->getStatusCode();

        $data = [];

        if (isset($this->labels['scheme'])) {
            $data[$this->labels['scheme']] = $scheme;
        }
        if (isset($this->labels['host'])) {
            $data[$this->labels['host']] = $host;
        }
        if (isset($this->labels['path'])) {
            $data[$this->labels['path']] = $path;
        }
        if (isset($this->labels['method'])) {
            $data[$this->labels['method']] = $method;
        }
        if (isset($this->labels['status'])) {
            $data[$this->labels['status']] = $status;
        }

        return $data;
    }
}
