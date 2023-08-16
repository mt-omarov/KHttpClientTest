<?php

namespace Kaa\HttpClient\Components\Exception;
use Kaa\HttpClient\Components\TraitHttpException;

final class ClientException extends RuntimeException
{
    use TraitHttpException;
}