<?php

namespace Kaa\HttpClient\Contracts;

interface ChunkInterface
{
    public function isTimeout(): bool;

    public function isFirst(): bool;

    public function isLast(): bool;

    public function getInformationalStatus(): ?array;

    public function getContent(): string;

    public function getOffset(): int;

    public function getError(): ?string;

    public function didThrow(): bool;
}