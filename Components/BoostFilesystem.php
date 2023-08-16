<?php

namespace Kaa\HttpClient\Components;
class BoostFilesystem
{
    public static function load()
    {
        \FFI::load(__DIR__ . '/libboost.h');
    }

    public function __construct()
    {
        $this->libboost = \FFI::scope("libboost");
    }

    public function SysGetTempDirPath(): ?string
    {
        return $this->libboost->temp_directory_path();
    }

    /** @var ffi_scope<libboost> */
    private $libboost;
}