<?php

namespace Anik\Laravel\Prometheus\Extractors;

use Illuminate\Contracts\Support\Arrayable;

class Response implements Arrayable
{
    /** @var \Symfony\Component\HttpFoundation\Response | \Illuminate\Http\Response $response */
    protected $response;
    protected array $labels;

    public function __construct($response, array $labels = [])
    {
        $this->response = $response;
        $this->labels = $labels;
    }

    public function toArray(): array
    {
        return [
            ($this->labels['status'] ?? 'status') => $this->response->getStatusCode(),
        ];
    }
}
