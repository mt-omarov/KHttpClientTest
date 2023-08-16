<?php

namespace Kaa\HttpClient\Components;

final class Canary
{
    private \Closure $canceller;

    public function __construct(\Closure $canceller)
    {
        $this->canceller = $canceller;
    }

    public function cancel(): void
    {
        if (isset($this->canceller)) {
            $canceller = $this->canceller;
            unset($this->canceller);
            $canceller();
        }
    }

    public function __destruct()
    {
        $this->cancel();
    }
}