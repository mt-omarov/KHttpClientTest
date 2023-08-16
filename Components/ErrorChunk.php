<?php

namespace Kaa\HttpClient\Components;
use Kaa\HttpClient\Contracts\ChunkInterface;
use Exception;
use Throwable;
use Kaa\HttpClient\Components\Exception\TransportException;
use Kaa\HttpClient\Components\Exception\TimeoutException;

class ErrorChunk implements ChunkInterface
{
    private bool $didThrow = false;
    private int $offset;
    private string $errorMessage;
    private Throwable $error;

    public function __construct(int $offset, ?Throwable $error = null, ?string $errorMessage = null)
    {
        $this->offset = $offset;

        if ($errorMessage) {
            $this->errorMessage = $errorMessage;
        } elseif ($error) {
            $this->error = $error;
            $this->errorMessage = $error->getMessage();
        }
        else return;
    }

    public function isTimeout(): bool
    {
        $this->didThrow = true;

        if (null !== $this->error) {
            throw new TransportException($this->errorMessage, 0);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isFirst(): bool
    {
        $this->didThrow = true;
        throw null !== $this->error ? new TransportException($this->errorMessage, 0) : new TimeoutException($this->errorMessage);
    }

    /**
     * {@inheritdoc}
     */
    public function isLast(): bool
    {
        $this->didThrow = true;
        throw null !== $this->error ? new TransportException($this->errorMessage, 0) : new TimeoutException($this->errorMessage);
    }

    /**
     * {@inheritdoc}
     */
    public function getInformationalStatus(): ?array
    {
        $this->didThrow = true;
        throw null !== $this->error ? new TransportException($this->errorMessage, 0) : new TimeoutException($this->errorMessage);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): string
    {
        $this->didThrow = true;
        throw null !== $this->error ? new TransportException($this->errorMessage, 0) : new TimeoutException($this->errorMessage);
    }

    /**
     * {@inheritdoc}
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * {@inheritdoc}
     */
    public function getError(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return bool Whether the wrapped error has been thrown or not
     */
    public function didThrow(): bool
    {
        return $this->didThrow;
    }

//    public function __destruct()
//    {
//        if (!$this->didThrow) {
//            $this->didThrow = true;
//            throw null !== $this->error ? new TransportException($this->errorMessage, 0, $this->error) : new TimeoutException($this->errorMessage);
//        }
//    }
}
