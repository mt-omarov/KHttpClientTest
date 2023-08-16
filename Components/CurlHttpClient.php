<?php

namespace Kaa\HttpClient\Components;
use Kaa\HttpClient\Contracts\HttpClientInterface;
use Kaa\HttpClient\Components\ExtractedHttpClient;
use Kaa\HttpClient\Components\CurlClientState;
use Kaa\HttpClient\Contracts\ResponseInterface;
use Kaa\HttpClient\Components\Exception\InvalidArgumentException;
use Kaa\HttpClient\Components\Exception\TransportException;

// in order to understand whether KPHP or PHP is being used
#ifndef KPHP
define('IS_PHP', true);
#endif

// use a ExtractedHttpClient class to store response options
class CurlHttpClient extends ExtractedHttpClient implements HttpClientInterface
{
    private Options $defaultOptions;
    private CurlClientState $multi;

    // public ?LoggerInterface $logger = null;
    public function __construct(?Options $defaultOptions, int $maxHostConnections = 6, int $maxPendingPushes = 50)
    {
        $this->defaultOptions = new Options();
        $this->defaultOptions->setExtra(['curl'=>[]]);

        if ($defaultOptions) {
            [, $this->defaultOptions] = self::prepareRequest(null, null, $defaultOptions, $this->$defaultOptions);
        }
        $this->multi = new CurlClientState($maxHostConnections, $maxPendingPushes);
    }

    public function getMulti(): CurlClientState
    {
        return $this->multi;
    }

    /**
     * @param mixed|null $redirectHeaders
     * @param CurlHandle $ch
     * @param string $location
     * @return string|null
     * @throws InvalidArgumentException
     */
    private static function redirectResolver(?array $redirectHeaders, CurlHandle $ch, string $location)
    {
        try {
            $location = self::parseUrl($location);
        } catch (InvalidArgumentException $e) {
            return null;
        }
        $host = parse_url('http:'.$location['authority'], PHP_URL_HOST);
        if ($redirectHeaders && $host) {
            $requestHeaders = $redirectHeaders['host'] === $host ? $redirectHeaders['with_auth'] : $redirectHeaders['no_auth'];
            $ch->curlSetOpt(CURLOPT_HTTPHEADER, $requestHeaders);
        }

        $url = self::parseUrl($ch->getInfo(CURLINFO_EFFECTIVE_URL));

        return implode('', $url);
    }

    public function request(string $method, ?string $url, ?Options $options = null): ResponseInterface
    {
        if (!$options) $options = new Options();
        [$url, $options] = self::prepareRequest($method, $url, $options, $this->defaultOptions);
        $scheme = $url['scheme'];
        $authority = $url['authority'];
        $host = (string) parse_url($authority, PHP_URL_HOST);

        $proxy = $options->getProxy()
            ?? ('https:' === $url['scheme'] ? $_SERVER['https_proxy'] ?? $_SERVER['HTTPS_PROXY'] ?? null : null)
            // Ignore HTTP_PROXY except on the CLI to work around httpoxy set of vulnerabilities
            ?? $_SERVER['http_proxy'] ?? (\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) ? $_SERVER['HTTP_PROXY'] ?? null : null) ?? $_SERVER['all_proxy'] ?? $_SERVER['ALL_PROXY'] ?? null;

        $url = implode('', $url);

        if (!Options::isset($options->getNormalizedHeader('user-agent'))) {
            $options->addToHeaders('User-Agent: Kaa HttpClient/Curl');
        }

        $curlopts = [
            CURLOPT_URL => $url,
            CURLOPT_TCP_NODELAY => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 0 < $options->getMaxRedirects() ? $options->getMaxRedirects() : 0,
            CURLOPT_COOKIEFILE => '', // Keep track of cookies during redirects
            CURLOPT_TIMEOUT => 0,
            CURLOPT_PROXY => $options->getProxy(),
            CURLOPT_NOPROXY => $options->getNoProxy() ?? $_SERVER['no_proxy'] ?? $_SERVER['NO_PROXY'] ?? '',
            //CURLOPT_SSL_VERIFYPEER => $options['verify_peer'],
            //CURLOPT_SSL_VERIFYHOST => $options['verify_host'] ? 2 : 0,
            //CURLOPT_CAINFO => $options['cafile'],
            //CURLOPT_CAPATH => $options['capath'],
            //CURLOPT_SSL_CIPHER_LIST => $options['ciphers'],
            //CURLOPT_SSLCERT => $options['local_cert'],
            //CURLOPT_SSLKEY => $options['local_pk'],
            //CURLOPT_KEYPASSWD => $options['passphrase'],
            //CURLOPT_CERTINFO => $options['capture_peer_cert_chain'],
        ];

        if (1.0 == (float) $options->getHttpVersion()) {
            $curlopts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_0;
        } elseif (1.1 == (float) $options->getHttpVersion()) {
            $curlopts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        }

        if (Options::isset($options->getAuthNtml())) {
            $curlopts[CURLOPT_HTTPAUTH] = CURLAUTH_NTLM;
            $curlopts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;

            if (\is_array($options->getAuthNtml())) {
                $count = \count($options->getAuthNtml());
                if ($count <= 0 || $count > 2) {
                    throw new InvalidArgumentException(sprintf('Option "auth_ntlm" must contain 1 or 2 elements, %d given.', $count));
                }

                $options->setAuthNtml(implode(':', $options->getAuthNtml()));
            }

            if (!\is_string($options->getAuthNtml())) {
                throw new InvalidArgumentException(sprintf('Option "auth_ntlm" must be a string or an array, "%s" given.', gettype($options->getAuthNtml())));
            }

            $curlopts[CURLOPT_USERPWD] = $options->getAuthNtml();
        }

        if (!ZEND_THREAD_SAFE) {
            $curlopts[CURLOPT_DNS_USE_GLOBAL_CACHE] = false;
        }

        if (\defined('CURLOPT_HEADEROPT')) {
            $curlopts[CURLOPT_HEADEROPT] = CURLHEADER_SEPARATE;
        }

        // curl's resolve feature varies by host:port but ours varies by host only, let's handle this with our own DNS map
        if (isset($this->multi->dnsCache->hostnames[$host])) {
            $options->addToResolve($host, $this->multi->dnsCache->hostnames[$host]);
        }

        if ($options->getResolve() || $this->multi->dnsCache->evictions) {
            // First reset any old DNS cache entries then add the new ones
            $resolve = $this->multi->dnsCache->evictions;
            $this->multi->dnsCache->evictions = [];
            $port = parse_url($authority, PHP_URL_PORT) ?: ('http:' === $scheme ? 80 : 443);

            $this->multi->handle->curlClose();
            $this->multi->handle = (new self(null))->multi->handle;

            foreach ($options->getResolve() as $host => $ip) {
                //$resolve[] = null === $ip ? "-$host:$port" : "$host:$port:$ip";
                $resolve[] = "$host:$port:$ip";
                $this->multi->dnsCache->hostnames[$host] = $ip;
                $this->multi->dnsCache->removals["-$host:$port"] = "-$host:$port";
            }

            $curlopts[CURLOPT_RESOLVE] = $resolve;
        }

        if ('POST' === $method) {
            // Use CURLOPT_POST to have browser-like POST-to-GET redirects for 301, 302 and 303
            $curlopts[CURLOPT_POST] = true;
        } elseif ('HEAD' === $method) {
            $curlopts[CURLOPT_NOBODY] = true;
        } else {
            $curlopts[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if ('\\' !== \DIRECTORY_SEPARATOR && $options->getTimeout() < 1) {
            $curlopts[CURLOPT_NOSIGNAL] = true;
        }

        if (\extension_loaded('zlib') && !Options::isset($options->getNormalizedHeader('accept-encoding'))) {
            $options->addToHeaders('Accept-Encoding: gzip'); // Expose only one encoding, some servers mess up when more are provided
        }

        foreach ($options->getHeaders() as $header) {
            if (':' === $header[-2] && \strlen($header) - 2 === strpos($header, ': ')) {
                // curl requires a special syntax to send empty headers
                $curlopts[CURLOPT_HTTPHEADER][] = substr_replace($header, ';', -2);
            } else {
                $curlopts[CURLOPT_HTTPHEADER][] = $header;
            }
        }

        // Prevent curl from sending its default Accept and Expect headers
        foreach (['accept', 'expect'] as $header) {
            if (!Options::isset($options->getElementNormalizedHeader($header, 0))) {
                $curlopts[CURLOPT_HTTPHEADER][] = $header.':';
            }
        }

        if (!\is_string($body = $options->getBody())) {
            if (is_int($body)) {
                $curlopts[CURLOPT_INFILE] = $body;
            }
            #ifndef KPHP
            elseif (\is_resource($body)) {
                $curlopts[CURLOPT_INFILE] = $body;
            }
            #endif

            if ($tempNHeadersFirstContentLength = $options->getElementNormalizedHeader('content-length', 0)) {
                $curlopts[CURLOPT_INFILESIZE] = substr($tempNHeadersFirstContentLength, \strlen('Content-Length: '));
            } elseif ($options->getNormalizedHeader('transfer-encoding')) {
                $curlopts[CURLOPT_HTTPHEADER][] = 'Transfer-Encoding: chunked'; // Enable chunked request bodies
            }

            if ('POST' !== $method) {
                $curlopts[CURLOPT_UPLOAD] = true;
            }
        } elseif ('' !== $body || 'POST' === $method) {
            $curlopts[CURLOPT_POSTFIELDS] = $body;
        }

        if ($options->getPeerFingerprint()) {
            $fingerPrint = $options->getPeerFingerprintElement('pin-sha256');
            if (!$fingerPrint) {
                throw new TransportException(__CLASS__.' supports only "pin-sha256" fingerprints.');
            }

            $curlopts[CURLOPT_PINNEDPUBLICKEY] = 'sha256//'.implode(';sha256//', $fingerPrint);
        }

        if ($bindTo = $options->getBindTo()) {
            $curlopts[file_exists($bindTo) ? CURLOPT_UNIX_SOCKET_PATH : CURLOPT_INTERFACE] = $bindTo;
        }

        if (0 < $options->getMaxDuration()) {
            $curlopts[CURLOPT_TIMEOUT_MS] = 1000 * $options->getMaxDuration();
        } elseif ($options->getMaxDuration() === -1) {
            $options->setMaxDuration(0);
        }

        $responsePushedResponse = null;
        if ($pushedResponse = $this->multi->pushedResponses[$url] ?? null) {
            unset($this->multi->pushedResponses[$url]);

            if (self::acceptPushForRequest($method, $options, $pushedResponse)) {
                //$this->logger && $this->logger->debug(sprintf('Accepting pushed response: "%s %s"', $method, $url));

                // Reinitialize the pushed response with request's options
                $ch = $pushedResponse->handle;
                $responsePushedResponse = $pushedResponse->response;
                $responsePushedResponse->__construct($this->multi, null, $url, $options);
            } else {
                //$this->logger && $this->logger->debug(sprintf('Rejecting pushed response: "%s"', $url));
                $pushedResponse = null;
            }
        }

        if (!$pushedResponse) {
            $ch = new CurlHandle();
            //$this->logger && $this->logger->info(sprintf('Request: "%s %s"', $method, $url));
        }

        $redirectResolverFunc = __CLASS__.'::redirectResolver';
        return $responsePushedResponse ?? new CurlResponse($this->multi, $ch, null, $options, $method, $redirectResolverFunc);
    }

    private static function acceptPushForRequest(string $method, Options $options, PushedResponse $pushedResponse): bool
    {
        if ('' !== $options->getBody() || $method !== $pushedResponse->requestHeaders[':method'][0]) {
            return false;
        }

        if (
            $options->getProxy() !== $pushedResponse->parentOptions->getProxy()
            || $options->getNoProxy() !== $pushedResponse->parentOptions->getNoProxy()
            || $options->getBindTo() !== $pushedResponse->parentOptions->getBindTo()
            || $options->getLocalCert() !== $pushedResponse->parentOptions->getLocalCert()
            || $options->getLocalPk() !== $pushedResponse->parentOptions->getLocalPk()
        ) {
            return false;
        }

        foreach (['authorization', 'cookie', 'range', 'proxy-authorization'] as $k) {
            $normalizedHeaders = $options->getNormalizedHeader($k) ?? [];
            foreach ($normalizedHeaders as $i => $v) {
                $normalizedHeaders[$i] = substr($v, \strlen($k) + 2);
            }

            if (($pushedResponse->requestHeaders[$k] ?? []) !== $normalizedHeaders) {
                return false;
            }
        }

        return true;
    }

}