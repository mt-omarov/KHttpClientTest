<?php

namespace Kaa\HttpClient\Components;

class FirstChunk extends DataChunk
{
    public function isFirst(): bool
    {
        return true;
    }
}