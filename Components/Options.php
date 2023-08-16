<?php

namespace Kaa\HttpClient\Components;
class Options
{
    const NULL = null;

    static array $fields = [
        'authBasic', 'authBearer', 'query', 'headers', 'normalizedHeaders', 'proxy',
        'noProxy', 'timeout', 'maxDuration', 'bindTo' , 'userData', 'maxRedirects',
        'httpVersion', 'baseUri', 'buffer', 'onProgress', 'resolve', 'json', 'body'];

    /** @var mixed $peerFingerprint */
    private $peerFingerprint = null;

    private string $authBasic = '';
    private string $authBearer = '';

    /** @var string[] $query */
    private array $query = [];

    /** @var string[] $headers */
    private array $headers = [];

    /** @var array<string, array<string>> $normalizedHeaders */
    private array $normalizedHeaders = [[]];

    private string $proxy = '';
    private string $noProxy = '';
    private float $timeout = -1;
    private float $maxDuration = -1;
    private string $bindTo = '0';
    private mixed $localCert = null;
    private mixed $localPk = null;


    /** @var mixed $userData  */
    private $userData = null;
    private int $maxRedirects = 20;
    private string $httpVersion = '';
    private string $baseUri = '';

    /** @var mixed $buffer */
    private $buffer = null;

    /** @var ?callable(mixed):mixed $onProgress  */
    private $onProgress = null;

    /** @var string[] $resolve */
    private array $resolve = [];
    private string $json = '';

    /** @var mixed $body */
    private $body = null;

    /** @var mixed $extra */
    private ?array $extra = null;

    /** @var mixed $authNtml */
    private $authNtml = null;

    /** @return mixed */
    public function getLocalPk()
    {
        return $this->localPk;
    }

    /** @return mixed */
    public function getLocalCert()
    {
        return $this->localCert;
    }

    /** @return mixed */
    public function getPeerFingerprint()
    {
        return $this->peerFingerprint;
    }

    public function getPeerFingerprintElement(string $key)
    {
        if (null === $this->peerFingerprint) return self::NULL;
        if (array_key_exists($key, $this->peerFingerprint)) {
            return $this->peerFingerprint[$key];
        }
        else return self::NULL;
    }

    /** @param mixed $fingerprint */
    public function setPeerFingerprint($fingerprint): self
    {
        $this->peerFingerprint = $fingerprint;
        return $this;
    }

    /** @return mixed */
    public function getExtra()
    {
        return $this->extra;
    }

    /** @param mixed $extra */
    public function setExtra($extra): self
    {
        $this->extra = $extra;
        return $this;
    }

    /** @param mixed $authNtml */
    public function setAuthNtml($authNtml): self
    {
        $this->authNtml = $authNtml;
        return $this;
    }

    public function getAuthNtml()
    {
        return $this->authNtml;
    }

    public function getAuthBasic(): string
    {
        return $this->authBasic;
    }

    public function setAuthBasic(string $authBasic): self
    {
        $this->authBasic = $authBasic;
        return $this;
    }

    public function getAuthBearer(): string
    {
        return $this->authBearer;
    }

    public function setAuthBearer(string $authBearer): self
    {
        $this->authBearer = $authBearer;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUserData(): mixed
    {
        return $this->userData;
    }

    /**
     * @param mixed $userData
     */
    public function setUserData(mixed $userData): self
    {
        $this->userData = $userData;
        return $this;
    }

    public function getMaxRedirects(): int
    {
        return $this->maxRedirects;
    }

    public function setMaxRedirects(int $maxRedirects): self
    {
        $this->maxRedirects = $maxRedirects;
        return $this;
    }

    public function getHttpVersion(): string
    {
        return $this->httpVersion;
    }

    public function setHttpVersion(string $httpVersion): self
    {
        $this->httpVersion = $httpVersion;
        return $this;
    }

    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function setBaseUri(string $baseUri): self
    {
        $this->baseUri = $baseUri;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBuffer(): mixed
    {
        return $this->buffer;
    }

    /**
     * @param mixed $buffer
     */
    public function setBuffer(mixed $buffer): self
    {
        $this->buffer = $buffer;
        return $this;
    }

    /**
     * @return ?callable(mixed):mixed
     */
    public function getOnProgress()
    {
        return $this->onProgress;
    }

    /**
     * @param ?callable(mixed):mixed $onProgress
     */
    public function setOnProgress($onProgress): self
    {
        $this->onProgress = $onProgress;
        return $this;
    }

    public function getProxy(): string
    {
        return $this->proxy;
    }

    public function setProxy(string $proxy): self
    {
        $this->proxy = $proxy;
        return $this;
    }

    public function getNoProxy(): string
    {
        return $this->noProxy;
    }

    public function setNoProxy(string $noProxy): self
    {
        $this->noProxy = $noProxy;
        return $this;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function setTimeout(float $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function getMaxDuration(): float
    {
        return $this->maxDuration;
    }

    public function setMaxDuration(float $maxDuration): self
    {
        $this->maxDuration = $maxDuration;
        return $this;
    }

    public function getBindTo(): string
    {
        return $this->bindTo;
    }

    public function setBindTo(string $bindTo): self
    {
        $this->bindTo = $bindTo;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @param string[] $query
     */
    public function setQuery(array $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function addToHeaders(string $header): self
    {
        $this->headers[] = $header;
        return $this;
    }

    /** @param string[] $headers */
    public function setHeaders(array $headers):self
    {
        $this->headers = $headers;
        return $this;
    }

    /** @param array<string, array<string>> $normalizedHeaders */
    public function setNormalizedHeaders(array $normalizedHeaders): self
    {
        $this->normalizedHeaders = $normalizedHeaders;
        return $this;
    }

    /** @param array<string> $header */
    public function setNormalizedHeader(string $key, array $header): self
    {
        $this->normalizedHeaders[$key] = $header;
        return $this;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getNormalizedHeaders(): array
    {
        return $this->normalizedHeaders;
    }

    /**
     * @return ?array<string>
     */
    public function getNormalizedHeader(string $key): ?array
    {
        if (array_key_exists($key, $this->normalizedHeaders)) {
            return $this->normalizedHeaders[$key];
        }
        else return self::NULL;
    }

    public function getElementNormalizedHeader(string $headerKey, int $key): ?string
    {
        if ($header = $this->getNormalizedHeader($headerKey)){
            return array_key_exists($key, $header) ? $header[$key] : self::NULL;
        }
        else return self::NULL;
    }

    /** @return mixed */
    public function getBody()
    {
        return $this->body;
    }

    /** @param mixed $body*/
    public function setBody($body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getJson(): string
    {
        return $this->json;
    }

    public function setJson(string $json): self
    {
        $this->json = $json;
        return $this;
    }

    /** @return string[] */
    public function getResolve(): array
    {
        return $this->resolve;
    }

    /** @param string[] $resolve */
    public function setResolve(array $resolve):self
    {
        $this->resolve = $resolve;
        return $this;
    }
    public function addToResolve(string $key, string $value): void
    {
        $this->resolve[$key] = $value;
    }

    public static function mergeOptions(self $lOptions, self $rOptions): self
    {
        foreach (self::$fields as $field)
        {
            // if an element of the lOptions is not set, then use the right
            switch ($field){
                case ("authBasic"):
                    if (!self::isset($lOptions->getAuthBasic()))
                        $lOptions->setAuthBasic($rOptions->getAuthBasic());
                    break;
                case ("authBearer"):
                    if (!self::isset($lOptions->getAuthBearer()))
                        $lOptions->setAuthBearer($rOptions->getAuthBearer());
                    break;
                case ("query"):
                    if (!self::isset($lOptions->getQuery()))
                        $lOptions->setQuery($rOptions->getQuery());
                    break;
                case ("headers"):
                    if (!self::isset($lOptions->getHeaders()))
                        $lOptions->setHeaders($rOptions->getHeaders());
                    break;
                case ("normalizedHeaders"):
                    if (!self::isset($lOptions->getNormalizedHeaders()))
                        $lOptions->setNormalizedHeaders($rOptions->getNormalizedHeaders());
                    break;
                case ("proxy"):
                    if (!self::isset($lOptions->getProxy()))
                        $lOptions->setProxy($rOptions->getProxy());
                    break;
                case ("noProxy"):
                    if (!self::isset($lOptions->getNoProxy()))
                        $lOptions->setNoProxy($rOptions->getNoProxy());
                    break;
                case ("timeout"):
                    if (!self::isset($lOptions->getTimeout()))
                        $lOptions->setTimeout($rOptions->getTimeout());
                    break;
                case ("maxDuration"):
                    if (!self::isset($lOptions->getMaxDuration()))
                        $lOptions->setMaxDuration($rOptions->getMaxDuration());
                    break;
                case ("bindTo"):
                    if (!self::isset($lOptions->getBindTo()))
                        $lOptions->setBindTo($rOptions->getBindTo());
                    break;
                case ("userData"):
                    if (!self::isset($lOptions->getUserData()))
                        $lOptions->setUserData($rOptions->getUserData());
                    break;
                case ("maxRedirects"):
                    if (!self::isset($lOptions->getMaxRedirects()))
                        $lOptions->setMaxRedirects($rOptions->getMaxRedirects());
                    break;
                case ("httpVersion"):
                    if (!self::isset($lOptions->getHttpVersion()))
                        $lOptions->setHttpVersion($rOptions->getHttpVersion());
                    break;
                case ("baseUri"):
                    if (!self::isset($lOptions->getBaseUri()))
                        $lOptions->setBaseUri($rOptions->getBaseUri());
                    break;
                case ("buffer"):
                    if (!self::isset($lOptions->getBuffer()))
                        $lOptions->setBuffer($rOptions->getBuffer());
                    break;
                case ("onProgress"):
                    if (!$lOptions->getOnProgress())
                        $lOptions->setOnProgress($rOptions->getOnProgress());
                    break;
                case ("resolve"):
                    if (!self::isset($lOptions->getResolve()))
                        $lOptions->setResolve($rOptions->getResolve());
                    break;
                case ("json"):
                    if (!self::isset($lOptions->getJson()))
                        $lOptions->setJson($rOptions->getJson());
                    break;
                case ("body"):
                    if (!self::isset($lOptions->getBody()))
                        $lOptions->setBody($rOptions->getBody());
                    break;
            }
        }
        return $lOptions;
    }

    /** @param mixed $option */
    public static function isset($option): bool
    {
        if (is_array($option)){
            return ($option !== []);
        }
        elseif (is_string($option)){
            return $option !== '';
        }
        elseif (is_int($option)){
            return $option !== -1;
        }
        else return ($option !== null);
    }

    public function printOptions()
    {
        foreach (self::$fields as $field)
        {
            // если левый "массив" опций не установлен, используй правый
            switch ($field){
                case ("authBasic"):
                    var_dump($this->getAuthBasic());
                    break;
                case ("authBearer"):
                    var_dump($this->getAuthBearer());
                    break;
                case ("query"):
                    var_dump($this->getQuery());
                    break;
                case ("headers"):
                    var_dump($this->getHeaders());
                    break;
                case ("normalizedHeaders"):
                    var_dump($this->getNormalizedHeaders());
                    break;
                case ("proxy"):
                    var_dump($this->getProxy());
                    break;
                case ("noProxy"):
                    var_dump($this->getNoProxy());
                    break;
                case ("timeOut"):
                    var_dump($this->getTimeout());
                    break;
                case ("maxDuration"):
                    var_dump($this->getMaxDuration());
                    break;
                case ("bindTo"):
                    var_dump($this->getBindTo());
                    break;
                case ("userData"):
                    var_dump($this->getUserData());
                    break;
                case ("maxRedirects"):
                    var_dump($this->getMaxRedirects());
                    break;
                case ("httpVersion"):
                    var_dump($this->getHttpVersion());
                    break;
                case ("baseUri"):
                    var_dump($this->getBaseUri());
                    break;
                case ("buffer"):
                    var_dump($this->getBuffer());
                    break;
                case ("onProgress"):
                    //print(gettype($this->getOnProgress()));
                    break;
                case ("resolve"):
                    var_dump($this->getResolve());
                    break;
                case ("json"):
                    var_dump($this->getJson());
                    break;
                case ("body"):
                    var_dump($this->getBody());
                    break;
            }
        }
    }
}