<?php

namespace Kaa\HttpClient\Components;
use Kaa\HttpClient\Components\CurlClientState;

class RunningResponses
{
    private CurlClientState $multi;
    /** @var array<int, CurlResponse> $responses */
    public array $responses;

    public function __construct(CurlClientState $multi)
    {
        $this->multi = $multi;
        $this->responses = [];
    }

    public function setResponse(int $id, CurlResponse $response): self
    {
        $this->responses[$id] = $response;
        return $this;
    }

    /**
     * @return CurlResponse[]
     */
    public function getResponses(): array
    {
        return $this->responses;
    }

    public function getResponse(int $key): ?CurlResponse
    {
        if (isset($this->responses[$key])){
            return $this->responses[$key];
        } else {
            return null;
        }
    }

    public function getMulti(): CurlClientState
    {
        return $this->multi;
    }
}