<?php

namespace Kaa\HttpClient\Components;

class CurlHandle
{
    /** @var int $handle because in KPHP resources are just integers*/
    protected $handle; // do not specify the type to avoid conflicts between KPHP and PHP

    /**
     * @param string|null $url
     * @param int|null $handle again: do not specify the type
     */
    public function __construct(?string $url = null, $handle = null)
    {
        // creates a curl session with url or null or stores the transferred one
        $handle ? $this->handle = $handle : $this->handle = curl_init($url);
    }

    /**
     * @return int KPHP will figure out
     * that it is an integer type, and php will figure out that it is a resource
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * @param int $option
     * @param mixed $value but KPHP cannot yet store event handlers (functions) to handle HTTP/2
     * @return bool
     */
    public function curlSetOpt(int $option, $value): bool
    {
        return curl_setopt($this->handle, $option, $value);
    }

    public function curlSetOptArray(array $optins): bool
    {
        return curl_setopt_array($this->handle, $optins);
    }

    public function curlClose(): void
    {
        curl_close($this->handle);
    }

    public function getInfo(?int $option = null)
    {
        return curl_getinfo($this->handle, $option);
    }
}