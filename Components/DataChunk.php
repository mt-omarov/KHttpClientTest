<?php

namespace Kaa\HttpClient\Components;

use Kaa\HttpClient\Contracts\ChunkInterface;

class DataChunk implements ChunkInterface
{
    private int $offset = 0;
    private string $content = '';

    public function __construct(int $offset = 0, string $content = '')
    {
        $this->offset = $offset;
        $this->content = $content;
    }

    public function isTimeout(): bool
    {
        return false;
    }

    public function isFirst(): bool
    {
        return false;
    }

    public function isLast(): bool
    {
        return false;
    }

    // Needs for implementing ChunkInterface without getting errors
    public function getInformationalStatus(): ?array
    {
        return null;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getError(): ?string
    {
        return null;
    }

    public function didThrow(): bool
    {
        return false;
    }

}