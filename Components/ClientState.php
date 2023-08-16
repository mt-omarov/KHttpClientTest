<?php

namespace Kaa\HttpClient\Components;

class ClientState
{
    /** @var array<int,HandleActivity[]> $handlesActivity */
    public array $handlesActivity = [];

    /** @var array<tuple(CurlHandle, Options)> $openHandles */
    public array $openHandles = [];
    public ?float $lastTimeout = null;
}