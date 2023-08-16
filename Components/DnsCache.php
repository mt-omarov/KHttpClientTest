<?php

namespace Kaa\HttpClient\Components;

final class DnsCache
{
    /** @var string[] */
    public array $hostnames = [];

    /** @var string[] */
    public array $removals = [];

    /** @var string[] */
    public array $evictions = [];
}