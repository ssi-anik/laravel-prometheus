<?php

namespace Anik\Laravel\Prometheus\Extractors;

use Illuminate\Contracts\Support\Arrayable;

class Response implements Arrayable
{
    /** @var \Symfony\Component\HttpFoundation\Response | \Illuminate\Http\Response $response */
    protected $response;
    protected array $mapper;

    public function __construct($response, array $mapper = [])
    {
        $this->response = $response;
        $this->mapper = $mapper;
    }

    public function toArray(): array
    {
        return [
            ($this->mapper['status'] ?? 'status') => $this->response->getStatusCode(),
        ];
    }
}
