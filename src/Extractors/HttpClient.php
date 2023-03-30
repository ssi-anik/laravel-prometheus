<?php

namespace Anik\Laravel\Prometheus\Extractors;

use GuzzleHttp\TransferStats;
use Illuminate\Contracts\Support\Arrayable;

class HttpClient implements Arrayable
{
    protected TransferStats $stats;
    protected array $naming;

    public function __construct(TransferStats $stats, array $naming = [])
    {
        $this->stats = $stats;
        $this->naming = $naming;
    }

    public function toArray(): array
    {
        $data = [];

        if (isset($this->naming['scheme'])) {
            $data[$this->naming['scheme']] = '';
        }
        if (isset($this->naming['host'])) {
            $data[$this->naming['host']] = '';
        }
        if (isset($this->naming['path'])) {
            $data[$this->naming['path']] = '';
        }
        if (isset($this->naming['method'])) {
            $data[$this->naming['method']] = '';
        }
        if (isset($this->naming['status'])) {
            $data[$this->naming['status']] = '';
        }

        return $data;
    }
}
