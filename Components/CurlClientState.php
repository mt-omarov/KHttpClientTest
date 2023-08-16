<?php

namespace Kaa\HttpClient\Components;

class CurlClientState extends ClientState
{
    // instead of using raw resource type, which is an int in KPHP, use a CurlHandle child class
    public ?CurlMultiHandle $handle;

    /** @var PushedResponse[] */
    public $pushedResponses = [];
    /** @var DnsCache */
    public $dnsCache;

    public function __construct(int $maxHostConnections, int $maxPendingPushes)
    {
        $this->handle = new CurlMultiHandle();
        $this->dnsCache = new DnsCache();
        $this->reset(); // needs to clear states
        if (\defined('CURLPIPE_MULTIPLEX')) {
            $this->handle->curlSetOpt(\CURLMOPT_PIPELINING, \CURLPIPE_MULTIPLEX);
        }
        if (\defined('CURLMOPT_MAX_HOST_CONNECTIONS')) {
            $maxHostConnections = $this->handle->curlSetOpt(\CURLMOPT_MAX_HOST_CONNECTIONS, 0 < $maxHostConnections ? $maxHostConnections : \PHP_INT_MAX) ? 0 : $maxHostConnections;
        }
        if (\defined('CURLMOPT_MAXCONNECTS') && 0 < $maxHostConnections) {
            $this->handle->curlSetOpt(\CURLMOPT_MAXCONNECTS, $maxHostConnections);
        }
    }

    public function reset(): void
    {
        foreach ($this->pushedResponses as $response) {
            $this->handle->curlMultiRemoveHandle($response->handle->getHandle());
            $response->handle->curlClose();
        }

        $this->pushedResponses = [];
        $this->dnsCache->evictions = $this->dnsCache->evictions ?: $this->dnsCache->removals;
        $this->dnsCache->removals = $this->dnsCache->hostnames = [];

        // We need to add this functionality to runtime of KPHP.
        //$this->handle->curlSetOpt(CURLMOPT_PUSHFUNCTION, null);
        $active = 0;
        while (CURLM_CALL_MULTI_PERFORM === $this->handle->curlMultiExec($active));
    }

    /**
     * @param CurlHandle $parent
     * @param $pushed
     * @param array $requestHeaders
     * @param int $maxPendingPushes
     * @return int
     */
    public static function handlePush($parent, $pushed, array $requestHeaders, int $maxPendingPushes): int
    {
        $headers = [];
        $origin = $parent->getInfo(\CURLINFO_EFFECTIVE_URL);

        foreach ($requestHeaders as $h) {
            if (false !== $i = strpos($h, ':', 1)) {
                $headers[substr($h, 0, $i)][] = substr($h, 1 + $i);
            }
        }

        if (!isset($headers[':method']) || !isset($headers[':scheme']) || !isset($headers[':authority']) || !isset($headers[':path'])) {
            // опущена работа с логированием через поле $this->logger
            //$this->logger?->debug(sprintf('Rejecting pushed response from "%s": pushed headers are invalid', $origin));
            return \CURL_PUSH_DENY;
        }

        $url = $headers[':scheme'][0].'://'.$headers[':authority'][0];

        if (!str_starts_with($origin, $url.'/')) {
            // for now, omitting the logging work
            //$this->logger?->debug(sprintf('Rejecting pushed response from "%s": server is not authoritative for "%s"', $origin, $url));
            return \CURL_PUSH_DENY;
        }

        // The $this->pushedResponses array is omitted due to the lack of functionality to get the index via key().
        // There is a need to redo the function and pass the key of the current element as a parameter

        //if ($maxPendingPushes <= \count($this->pushedResponses)) {
        //    $fifoUrl = key($this->pushedResponses);
        //    unset($this->pushedResponses[$fifoUrl]);
        //    $this->logger?->debug(sprintf('Evicting oldest pushed response: "%s"', $fifoUrl));
        //}

        $url .= $headers[':path'][0];
        //$this->logger?->debug(sprintf('Queueing pushed response: "%s"', $url));

        // The use of $this->openHandles from the parent class is omitted.
        //$this->pushedResponses[$url] = new PushedResponse(new CurlResponse($this, $pushed), $headers, $this->openHandles[(int) $parent][1] ?? [], $pushed);

        return \CURL_PUSH_OK;
    }
}