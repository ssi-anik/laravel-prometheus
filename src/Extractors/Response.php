<?php

namespace Anik\Laravel\Prometheus\Extractors;

use Illuminate\Contracts\Support\Arrayable;

class Response implements Arrayable
{
    /** @var \Symfony\Component\HttpFoundation\Response | \Illuminate\Http\Response $response */
    protected $response;
    protected array $naming;

    public function __construct($response, array $naming = [])
    {
        $this->response = $response;
        $this->naming = $naming;
    }

    public function toArray(): array
    {
        return [
            ($this->naming['status'] ?? 'status') => $this->response->getStatusCode(),
        ];
    }
}
