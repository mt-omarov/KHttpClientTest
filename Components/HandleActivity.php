<?php

namespace Kaa\HttpClient\Components;

use Kaa\HttpClient\Contracts\ChunkInterface;
use Exception;
use Error;

class HandleActivity
{
    private ?string $message;
    private ?Exception $exception;
    private ?ChunkInterface $chunk; // DataChunk, LastChunk, FirstChunk, ErrorChunk
    private ?Error $error;

    public function __construct()
    {
        $this->reset();
    }

    public function setMessage(string $message): self
    {
        $this->reset();
        $this->message = $message;
        return $this;
    }

    public function setException(Exception $exception): self
    {
        $this->reset();
        $this->exception = $exception;
        return $this;
    }

    public function setChunk(ChunkInterface $chunk): self
    {
        $this->reset();
        $this->chunk = $chunk;
        return $this;
    }

    public function setError(Error $error): self
    {
        $this->reset();
        $this->error = $error;
        return $this;
    }

    public function reset()
    {
        $this->message = null;
        $this->exception = null;
        $this->chunk = null ;
        $this->error = null;
    }

    public function getActivityMessage(): ?string
    {
        return $this->message;
    }

    public function getActivityException(): ?Exception
    {
        return $this->exception;
    }

    public function getActivityChunk(): ?ChunkInterface
    {
        return $this->chunk;
    }

    public function getActivityError(): ?Error
    {
        return $this->error;
    }

    public function isNull()
    {
        if ($this->chunk || $this->exception || $this->message || $this->error) {
            return false;
        } else {
            return true;
        }
    }
}