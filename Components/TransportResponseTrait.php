<?php

namespace Kaa\HttpClient\Components;
trait TransportResponseTrait
{
    private Canary $canary;
    private array $headers = [];
    private array $info = [
        'response_headers' => [],
        'http_code' => 0,
        'error' => null,
        'canceled' => false,
    ];

    /** @var object|resource */
    private $handle;
    private int|string $id;
    private ?float $timeout = 0;
}