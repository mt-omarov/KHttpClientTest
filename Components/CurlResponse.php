<?php

namespace Kaa\HttpClient\Components;
use Kaa\HttpClient\Components\Exception\InvalidArgumentException;
use Kaa\HttpClient\Components\Exception\TransportException;
use Kaa\HttpClient\Contracts\ResponseInterface;

use Kaa\HttpClient\Components\Exception\ClientException;
use Kaa\HttpClient\Components\Exception\ServerException;
use Kaa\HttpClient\Components\Exception\RedirectionException;

class CurlResponse implements ResponseInterface
{
    private CurlClientState $multi;
    private int $id;

    /** @var string[][] $headers */
    private $headers = [];

    /** @var mixed $info */
    private $info = [
        'response_headers' => [],
        'http_code' => 0,
        'error' => null,
        'canceled' => false,
    ];
    private CurlHandle $handle;
    private float $timeout = 0;
    private int $offset = 0;
    /** @var callable(self): bool */
    private $initializer;
//    private $inflate; // InflateContext | bool

    /** @var ?int $content in PHP it's a resource type, in KPHP – int*/
    private $content;

    /** @var mixed $finalInfo */
    private $finalInfo = [];

    /** @var mixed $debugBuffer */
    private $debugBuffer;
    private static bool $performing = false;

    /**
     * @param CurlClientState $multi
     * @param CurlHandle|null $ch
     * @param string|null $url
     * @param Options|null $options
     * @param string $method
     * @param ?string $resolveRedirect stores name of CurlHttpClient::ResolveRedirect(mixed, CurlHandle, string):string|null function
     * @throws InvalidArgumentException
     */
    public function __construct(CurlClientState $multi, ?CurlHandle $ch, ?string $url = null, ?Options $options = null, string $method = 'GET', ?string $resolveRedirect = null)
    {
        $this->multi = $multi;
        if ($ch){
            $this->handle = $ch;
        }
        elseif ($url){
            $this->info['url'] = $url;
            $ch = $this->handle;
        }
        else{
            throw new InvalidArgumentException(sprintf("Incorrect %s constructor call, one of the required parameters CurlHandle | string url was not passed.", self::class));
        }

        // an attempt to use FFI for creating a temporary file via temporary directory
        //BoostFilesystem::load();
        //$libboost = new BoostFilesystem();
        //$this->debugBuffer = tempnam($libboost->SysGetTempDirPath(), "temp");

        // attempt to use file and forward error outputs of stream to it
        //$this->debugBuffer = fopen('debugBuffer', 'w+');
        //$ch->curlSetOpt(CURLOPT_VERBOSE, true);
        //$ch->curlSetOpt(CURLOPT_STDERR, $this->debugBuffer); //отсутствует в KPHP

        // Creates a temp file from user defined DIR for debugging, this means that we should store
        // a constant of the user folder path and a bool option for turn on/off debugging.
        $this->debugBuffer = fopen(tempnam(__DIR__, "temp"), 'w+');

        $this->id = $id = (int) $ch->getHandle();
        //$this->shouldBuffer = $options['buffer'] ?? true;
        $this->timeout = $options ? $options->getTimeout() !== -1 ?  $options->getTimeout() : 0 : $this->timeout;
        $this->info['http_method'] = $method;
        $this->info['user_data'] = $options ? $options->getUserData() : null;
        $this->info['start_time'] = $this->info['start_time'] ?? microtime(true);

        if (!$this->info['response_headers']) {
            // Used to keep track of what we're waiting for
            $ch->curlSetOpt(CURLOPT_PRIVATE, \in_array($method, ['GET', 'HEAD', 'OPTIONS', 'TRACE'], true) && 1.0 < (float)($options->getHttpVersion() ?? 1.1) ? 'H2' : 'H0');
        }

        // there is no implementation of curl_pause function yet in KPHP
//        $ch->curlPause(CURLPAUSE_CONT);
//        $redirectHeaders = [];
//        if (0 < $options->getMaxRedirects()) {
//            $redirectHeaders['host'] = $host;
//            $redirectHeaders['with_auth'] = $redirectHeaders['no_auth'] = array_filter($options->getHeaders(), static fn ($h) => 0 !== stripos($h, 'Host:'));
//
//            if (Options::isset($options->getElementNormalizedHeader('authorization', 0)) || Options::isset($options->getElementNormalizedHeader('cookie', 0))) {
//                $redirectHeaders['no_auth'] = array_filter($options->getHeaders(), static fn ($h) => 0 !== stripos($h, 'Authorization:') && 0 !== stripos($h, 'Cookie:'));
//            }
//        }

        $this->initializer = static function (self $response) {
            $waitFor = $response->handle->getInfo(CURLINFO_PRIVATE);

            return 'H' === $waitFor[0];
        };

        // Schedule the request in a non-blocking way
        // $multi->openHandles[$id] = tuple($ch, $options);
        $multi->handle->curlMultiAddHandle($ch->getHandle());
    }

    public function getHandleId() {
        return $this->handle->getHandle();
    }

    public function getMulti() {
        return $this->multi;
    }

    public function getInitializer()
    {
        return $this->initializer;

    }

    public function setInfoParam(string $key, $value): self
    {
        $this->info[$key] = $value;
        return $this;
    }

    public function getInfoParam(?string $key = null): mixed
    {
        return $key && (isset($this->info[$key])) ? $this->info[$key] : $this->info;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function getContentParam()
    {
        return $this->content;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function getStatusCode(): int
    {
        if ($this->initializer) {
            self::initialize($this);
        }

        return (int) $this->info['http_code'];
    }

    public function getContent(bool $throw = true): string
    {
        if ($this->initializer) {
            self::initialize($this);
        }

        if ($throw) {
            $this->checkStatusCode();
        }

        if (null === $this->content) {
            $content = null;

            $iterator = new StreamIterator([$this]);
            while ($iterator->hasResponses()) {
                $chunk = $iterator->stream();
                if ($chunk === null) {
                    break;
                } elseif (!$chunk->isLast()) {
                    $content .= $chunk->getContent();
                }
            }

            if (null !== $content) {
                return $content;
            }

            if ('HEAD' === $this->info['http_method'] || \in_array($this->info['http_code'], [204, 304], true)) {
                return '';
            }

            throw new TransportException('Cannot get the content of the response twice: buffering is disabled.');
        }

        $iterator = new StreamIterator([$this]);
        while ($iterator->hasResponses()) {
            $chunk = $iterator->stream();
            if ($chunk === null) break;
            // Chunks are buffered in $this->content already
        }

        rewind($this->content);

        $result = '';
        while (!feof($this->content)) {
            $result .= fread($this->content, 8192);
        }
        return $result;
    }
    public function getHeaders(bool $throw = true): array
    {
        if ($this->initializer) {
            self::initialize($this);
        }

        if ($throw) {
            $this->checkStatusCode();
        }

        return $this->headers;
    }

    private function checkStatusCode()
    {
        if (500 <= $this->getInfo('http_code')) {
            throw new ServerException($this);
        }

        if (400 <= $this->getInfo('http_code')) {
            throw new ClientException($this);
        }

        if (300 <= $this->getInfo('http_code')) {
            throw new RedirectionException($this);
        }
    }

    private static function initialize(self $response): void
    {
        if (null !== $response->getInfo('error')) {
            throw new TransportException($response->getInfo('error'));
        }

        try {
            if (($response->initializer)($response)) {
                $iterator = new StreamIterator([$response], 0.0);
                while ($iterator->hasResponses()) {
                    $chunk = $iterator->stream();
                    if ($chunk === null) {
                        break;
                    } elseif ($chunk->isFirst()) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Persist timeouts thrown during initialization
            $response->info['error'] = $e->getMessage();
            $response->close();
            throw $e;
        }

        $response->initializer = null;
    }

    public function getInfo(string $type = null)
    {
        if (!$this->finalInfo) {
            $info = array_merge($this->finalInfo, $this->handle->getInfo());
            $info['url'] = $this->info['url'] ?? $info['url'];
            $info['redirect_url'] = $this->info['redirect_url'] ?? null;

            // workaround curl not subtracting the time offset for pushed responses
            if (isset($this->info['url']) && $info['start_time'] / 1000 < $info['total_time']) {
                $info['total_time'] -= $info['starttransfer_time'] ?: $info['total_time'];
                $info['starttransfer_time'] = 0.0;
            }

            rewind($this->debugBuffer);
            $info['debug'] = '';
            while (!feof($this->debugBuffer)) {
                $info['debug'] .= fread($this->debugBuffer, 8192);
            }
            $waitFor = $this->handle->getInfo( CURLINFO_PRIVATE);

            if (!is_bool($waitFor)){
                if ('H' !== $waitFor[0] && 'C' !== $waitFor[0]) {
                    $this->handle->curlSetOpt(CURLOPT_VERBOSE, false);
                    fseek($this->debugBuffer, 0);
                    fwrite($this->debugBuffer, "");
                    fflush($this->debugBuffer);
                    $this->finalInfo = $info;
                }
            }
        }

        return null !== $type ? $this->finalInfo[$type] ?? null : $this->finalInfo;
    }

    /**
     * @param CurlHandle $ch
     * @param string $data
     * @param mixed $info
     * @param mixed $headers
     * @param Options|null $options
     * @param CurlClientState $multi
     * @param int $id
     * @param string|null $location
     * @param callable(mixed, CurlHandle, string):string|null $resolveRedirect
     * @param mixed $redirectHeaders
     * @return int
     */
    private static function parseHeaderLine(CurlHandle $ch, string $data, array &$info, array &$headers, ?Options $options, CurlClientState $multi, int $id, ?string &$location, callable $resolveRedirect, array $redirectHeaders): int
    {
        $waitFor = @$ch->getInfo(\CURLINFO_PRIVATE) ?: '_0';

        if ('H' !== $waitFor[0]) {
            return \strlen($data); // Ignore HTTP trailers
        }

        if ('' !== $data) {
            // Regular header line: add it to the list
            $tempDebug = '';
            self::addResponseHeaders([$data], $info, $headers, $tempDebug);

            if (!str_starts_with($data, 'HTTP/')) {
                if (0 === stripos($data, 'Location:')) {
                    $location = trim(substr($data, 9));
                }

                return \strlen($data);
            }


            if (300 <= $info['http_code'] && $info['http_code'] < 400) {
                if ($ch->getInfo(\CURLINFO_REDIRECT_COUNT) === $options->getMaxRedirects()) {
                    $ch->curlSetOpt(\CURLOPT_FOLLOWLOCATION, false);
                } elseif (303 === $info['http_code'] || ('POST' === $info['http_method'] && \in_array($info['http_code'], [301, 302], true))) {
                    $ch->curlSetOpt(\CURLOPT_POSTFIELDS, '');
                }
            }

            return \strlen($data);
        }
        // End of headers: handle informational responses, redirects, etc.
        $statusCode = $ch->getInfo( \CURLINFO_RESPONSE_CODE);
        if (200 > $statusCode) {
            //$multi->handlesActivity[$id][] = new InformationalChunk($statusCode, $headers);
            $location = null;

            return \strlen($data);
        }

        $info['redirect_url'] = null;

        if (300 <= $statusCode && $statusCode < 400 && null !== $location) {
            if ($noContent = 303 === $statusCode || ('POST' === $info['http_method'] && \in_array($statusCode, [301, 302], true))) {
                $info['http_method'] = 'HEAD' === $info['http_method'] ? 'HEAD' : 'GET';
                $ch->curlSetOpt( \CURLOPT_CUSTOMREQUEST, $info['http_method']);
            }
            $info['redirect_url'] = $resolveRedirect($redirectHeaders, $ch, $location);
            if (null === $info['redirect_url']) {
                $options['max_redirects'] = $ch->getInfo(\CURLINFO_REDIRECT_COUNT);
                $ch->curlSetOpt(\CURLOPT_FOLLOWLOCATION, false);
                $ch->curlSetOpt(\CURLOPT_MAXREDIRS, $options->getMaxRedirects());
            } else {
                $url = parse_url($location ?? ':');

                if (isset($url['host']) && null !== $ip = $multi->dnsCache->hostnames[$url['host'] = strtolower($url['host'])] ?? null) {
                    // Populate DNS cache for redirects if needed
                    $port = $url['port'] ?? ('http' === ($url['scheme'] ?? parse_url($ch->getInfo(\CURLINFO_EFFECTIVE_URL), \PHP_URL_SCHEME)) ? 80 : 443);
                    $ch->curlSetOpt(\CURLOPT_RESOLVE, ["{$url['host']}:$port:$ip"]);
                    $multi->dnsCache->removals["-{$url['host']}:$port"] = "-{$url['host']}:$port";
                }
            }
        }
//
//        if (401 === $statusCode && isset($options['auth_ntlm']) && 0 === strncasecmp($headers['www-authenticate'][0] ?? '', 'NTLM ', 5)) {
//            // Continue with NTLM auth
//        } elseif ($statusCode < 300 || 400 <= $statusCode || null === $location || curl_getinfo($ch, \CURLINFO_REDIRECT_COUNT) === $options['max_redirects']) {
//            // Headers and redirects completed, time to get the response's content
//            $multi->handlesActivity[$id][] = new FirstChunk();
//
//            if ('HEAD' === $info['http_method'] || \in_array($statusCode, [204, 304], true)) {
//                $waitFor = '_0'; // no content expected
//                $multi->handlesActivity[$id][] = null;
//                $multi->handlesActivity[$id][] = null;
//            } else {
//                $waitFor[0] = 'C'; // C = content
//            }
//
//            curl_setopt($ch, \CURLOPT_PRIVATE, $waitFor);
//        } elseif (null !== $info['redirect_url'] && $logger) {
//            $logger->info(sprintf('Redirecting: "%s %s"', $info['http_code'], $info['redirect_url']));
//        }
//
//        $location = null;
//
        return \strlen($data);
    }

    /**
     * @param mixed $responseHeaders
     * @param mixed $info
     * @param mixed $headers
     * @param string $debug
     * @return void
     */
    private static function addResponseHeaders(array $responseHeaders, array &$info, array &$headers, string &$debug): void
    {
        foreach ($responseHeaders as $h) {
            if (11 <= \strlen($h) && '/' === $h[4] && preg_match('#^HTTP/\d+(?:\.\d+)? (\d\d\d)(?: |$)#', $h, $m)) {
                if ($headers) {
                    $debug .= "< \r\n";
                    $headers = [];
                }
                $info['http_code'] = (int) $m[1];
            } elseif (2 === \count($m = explode(':', $h, 2))) {
                $headers[strtolower($m[0])][] = ltrim($m[1]);
            }

            $debug .= "< {$h}\r\n";
            $info['response_headers'][] = $h;
        }

        $debug .= "< \r\n";
    }

    /**
     * @param CurlResponse $response
     * @param array<int, RunningResponses> $runningResponses
     * @return void
     */
    public static function schedule(self $response, array &$runningResponses): void
    {
        if (isset($runningResponses[$i = (int) $response->multi->handle->getHandle()])) {
            $runningResponses[$i]->setResponse($response->id, $response);
        } else {
            $runningResponses[$i] = new RunningResponses($response->multi);
            $runningResponses[$i]->setResponse($response->id, $response);
        }

        if ('_0' === $response->handle->getInfo(CURLINFO_PRIVATE)) {
            // Response already completed
            $response->multi->handlesActivity[$response->id][] = new HandleActivity(); // equal to store null
            $response->multi->handlesActivity[$response->id][] = null !== $response->info['error'] ? (new HandleActivity())->setException(new TransportException($response->info['error'])) : null;
        }
    }

    /**
     * @param array<self> $responses
     * @param float|null $timeout
     * @return void
     */
    public static function oldStream(array $responses, ?float $timeout = null)
    {
        /** @var RunningResponses[] $runningResponses */
        $runningResponses = [];

        foreach ($responses as $response) {
            self::schedule($response, $runningResponses);
        }
        $lastActivity = microtime(true);
        $elapsedTimeout = 0;
        while (true) {
            $timeoutMax = 0;
            $timeoutMin = $timeout ?? INF;
            foreach ($runningResponses as $i => $runningResponse) {
                $multi = $runningResponse->getMulti();
                self::perform($multi, $runningResponse->responses);

                foreach ($runningResponse->responses as $j => $response) {
                    $timeoutMax = $timeout ?? max($timeoutMax, $response->timeout);
                    $timeoutMin = min($timeoutMin, $response->timeout, 1);
                    $chunk = null;

                    if (isset($multi->handlesActivity[$j])) {
                        // no-op
                    } elseif (!isset($multi->openHandles[$j])) {
                        unset($runningResponse->responses[$j]);
                        continue;
                    } elseif ($elapsedTimeout >= $timeoutMax) {
                        $multi->handlesActivity[$j] = [(new HandleActivity())->setChunk(new ErrorChunk($response->offset, null, sprintf('Idle timeout reached for "%s".', $response->getInfo('url'))))];
                    } else {
                        continue;
                    }
                    while ($multi->handlesActivity[$j] ?? false) {
                        $hasActivity = true;
                        $elapsedTimeout = 0;
                        if ($stringChunk = ($tempChunk = array_shift($multi->handlesActivity[$j]))->getActivityMessage()) {
                            if ('' !== $stringChunk && null !== $response->content && \strlen($stringChunk) !== fwrite($response->content, $stringChunk)) {
                                $multi->handlesActivity[$j] = [(new HandleActivity()), (new HandleActivity())->setException(new TransportException(sprintf('Failed writing %d bytes to the response buffer.', \strlen($stringChunk))))];
                                continue;
                            }

                            $chunkLen = \strlen($stringChunk);
                            $chunk = new DataChunk($response->offset, $stringChunk);
                            $response->offset += $chunkLen;
                        } elseif ($tempChunk->isNull()) {
                            // After array_shift the second element of the array become the first.
                            // If the previous one was null, then the current chunk should be an exception or an error.

                            // Here we try to get an exception,
                            // but in original code we also can get something else.
                            //
                            $e = $multi->handlesActivity[$j][0]->getActivityException() ?: $multi->handlesActivity[$j][0]->getActivityError();
                            unset($runningResponse->responses[$j], $multi->handlesActivity[$j]);
                            $response->close();

                            // the original library has here a block with comparing $e to null.
                            // remind: in original code $e stores a null|string|ChunkInterface|Exception|Error object.
                            if (null !== $e) {
                                // Because we are sure, that the object implements Throwable, we cat use getMessage.
                                $response->info['error'] = $e->getMessage();

                                // for now we don't understand how to get no exception object, but an error extended object.
                                // there is a problem with knowing, which classes with an Error parent can be stored in our array.
                                // that also means, that the HandleActivity class should have methods to store Error objects.
                                if ($e instanceof \Error) {
                                    throw $e;
                                }

                                $chunk = new ErrorChunk($response->offset, $e);
                            } else {
                                $chunk = new LastChunk($response->offset);
                            }
                        } elseif ($tempChunk->getActivityChunk() instanceof ErrorChunk) {
                            unset($responses[$j]);
                            $chunk = $tempChunk;
                            $elapsedTimeout = $timeoutMax;
                        } elseif ($tempChunk->getActivityChunk() instanceof FirstChunk) {
                            $chunk = $tempChunk;
                            // Here should be a code block with logging logic.
                            // .

                            // Here should be a code block with a buffering content logic.
                            // .

                            //yield $response => $chunk;

                            if ($response->initializer && null === $response->info['error']) {
                                // Ensure the HTTP status code is always checked
                                $response->getHeaders(true);
                            }
                            continue;
                        }
                        // here will be yield statement with returning chain of Response and ?ChunkInterface object;
                        //yield $response => $chunk;
                    }
                    unset($multi->handlesActivity[$j]);

                    if ($chunk instanceof ErrorChunk){
                        if (!$chunk->didThrow()) {
                            // Ensure transport exceptions are always thrown
                            $chunk->getContent();
                        }
                    }
                }

                if (!$responses) {
                    unset($runningResponses[$i]);
                }

                // Prevent memory leaks
                $multi->handlesActivity = $multi->handlesActivity ?: [];
                $multi->openHandles = $multi->openHandles ?: [];
            }

            if (!$runningResponses) {
                break;
            }

            if (-1 === self::select($multi, min($timeoutMin, $timeoutMax - $elapsedTimeout))) {
                usleep(min(500, 1E6 * $timeoutMin));
            }

            $elapsedTimeout = microtime(true) - $lastActivity;
        }
    }

    public static function select(CurlClientState $multi, float $timeout): int
    {
        if (\PHP_VERSION_ID < 70123 || (70200 <= \PHP_VERSION_ID && \PHP_VERSION_ID < 70211)) {
            // workaround https://bugs.php.net/76480
            $timeout = min($timeout, 0.01);
        }

        return (int) $multi->handle->curlMultiSelect($timeout);
    }

    public function close(): void
    {
//        $this->inflate = null;
        unset($this->multi->openHandles[$this->id], $this->multi->handlesActivity[$this->id]);
        $this->handle->curlSetOpt(CURLOPT_PRIVATE, '_0');

        if (self::$performing) {
            return;
        }
        $this->multi->handle->curlMultiRemoveHandle($this->handle->getHandle());
//        $this->handle->curlSetOptArray([
//            CURLOPT_NOPROGRESS => true,
//            CURLOPT_PROGRESSFUNCTION => null,
//            CURLOPT_HEADERFUNCTION => null,
//            CURLOPT_WRITEFUNCTION => null,
//            CURLOPT_READFUNCTION => null,
//            CURLOPT_INFILE => null,
//        ]);
    }

    /**
     * @param CurlClientState $multi
     * @param array<self> $responses
     * @param int|null $index
     * @return void
     */
    public static function perform(CurlClientState $multi, array &$responses, ?int $index = null): void
    {
        if (self::$performing) {
            if ($responses !== []) {
                if (defined('IS_PHP')) {
                    #ifndef KPHP
                    $response = $index ? $responses[$index] : $responses[array_key_first($responses)];
                    #endif
                } else {
                    $response = $index ? $responses[$index] : array_first_value($responses);
                }
                $multi->handlesActivity[(int)$response->handle->getHandle()][] = null;
                $multi->handlesActivity[(int)$response->handle->getHandle()][] = (new HandleActivity())->setException(new TransportException(sprintf('Userland callback cannot use the client nor the response while processing "%s".', $response->getInfo((string) CURLINFO_EFFECTIVE_URL))));
            }
            return;
        }
        try {
            self::$performing = true;
            $active = 0;
            while (CURLM_CALL_MULTI_PERFORM === ($err = $multi->handle->curlMultiExec($active)));

            if (\CURLM_OK !== $err) {
                throw new TransportException((string) CurlMultiHandle::curlMultiStrError((int)$err));
            }
            $tMsgCount = -1;
            while ($info = $multi->handle->curlMultiInfoRead($tMsgCount)) {
                $result = $info['result'];
                $id = $info['handle']; // for PHP the type of handle must be resource, but in KPHP – int
                $ch = new CurlHandle(null, $id);
                $waitFor = $ch->getInfo(CURLINFO_PRIVATE) ?: '_0';

                if (\in_array($result, [CURLE_SEND_ERROR, CURLE_RECV_ERROR, /*CURLE_HTTP2*/ 16, /*CURLE_HTTP2_STREAM*/ 92], true) && $waitFor[1] && 'C' !== $waitFor[0]) {
                    $multi->handle->curlMultiRemoveHandle($info['handle']);
                    $waitFor[1] = (string) ((int) $waitFor[1] - 1); // decrement the retry counter
                    $ch->curlSetOpt(CURLOPT_PRIVATE, $waitFor);

                    if ('1' === $waitFor[1]) {
                        $ch->curlSetOpt(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                    }
                    if (0 === $multi->handle->curlMultiAddHandle($ch->getHandle())){
                        continue;
                    }
                }
                $multi->handlesActivity[(int) $id][] = null;
                $multi->handlesActivity[(int) $id][] = (
                    \in_array($result, [CURLE_OK, CURLE_TOO_MANY_REDIRECTS], true)
                    || '_0' === $waitFor
                    || $ch->getInfo( CURLINFO_SIZE_DOWNLOAD) === $ch->getInfo(CURLINFO_CONTENT_LENGTH_DOWNLOAD)
                ) ? null : (new HandleActivity())->setException
                (
                    new TransportException(sprintf
                    (
                        '%s for "%s".',
                        CurlMultiHandle::curlMultiStrError($result),
                        $ch->getInfo(CURLINFO_EFFECTIVE_URL)
                    ))
                );
            }
        } catch (TransportException $e) {
        }
        self::$performing = false;
    }

}