<?php

namespace Kaa\HttpClient\Components;

final class PushedResponse
{
    public CurlResponse $response;

    /** @var string[] */
    public array $requestHeaders;
    public Options $parentOptions;

    /** @var CurlHandle */
    public $handle;

    /**
     * @param CurlResponse $response
     * @param array $requestHeaders
     * @param Options $parentOptions
     * @param CurlHandle $handle
     */
    public function __construct(CurlResponse $response, array $requestHeaders, Options $parentOptions, $handle)
    {
        $this->response = $response;
        $this->requestHeaders = $requestHeaders;
        $this->parentOptions = $parentOptions;
        $this->handle = $handle;
    }
}