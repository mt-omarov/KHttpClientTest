<?php

namespace Kaa\HttpClient\Components\Exception;
use Kaa\HttpClient\Components\TraitHttpException;

class ServerException extends RuntimeException
{
    use TraitHttpException;
}