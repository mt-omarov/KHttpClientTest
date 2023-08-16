<?php

namespace Kaa\HttpClient\Components\Exception;
use Kaa\HttpClient\Components\TraitHttpException;

class RedirectionException extends RuntimeException
{
    use TraitHttpException;
}