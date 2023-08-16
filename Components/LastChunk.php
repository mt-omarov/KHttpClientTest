<?php

namespace Kaa\HttpClient\Components;

class LastChunk extends DataChunk
{
    public function isLast(): bool
    {
        return true;
    }
}