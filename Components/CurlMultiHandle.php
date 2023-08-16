<?php

namespace Kaa\HttpClient\Components;

class CurlMultiHandle extends CurlHandle
{
    public function __construct()
    {
        $this->handle = curl_multi_init();
    }

    /**
     * @param int $option
     * @param mixed $value
     * @return bool
     */
    public function curlSetOpt(int $option, $value): bool
    {
        return curl_multi_setopt($this->handle, $option, $value);
    }

    /**
     * @param int $handle
     * @return false|int
     */
    public function curlMultiRemoveHandle($handle)
    {
        return curl_multi_remove_handle($this->handle, $handle);
    }

    /**
     * @param int $handle
     * @return int|false
     */
    public function curlMultiAddHandle($handle)
    {
        return curl_multi_add_handle($this->handle, $handle);
    }

    /**
     * @return int|false
     */
    public function curlMultiSelect(float $timeout)
    {
        return curl_multi_select($this->handle, $timeout);
    }

    public function curlClose(): void
    {
        curl_multi_close($this->handle);
    }

    /**
     * @param int $active
     * @return false|int
     */
    public function curlMultiExec(&$active): int
    {
        return curl_multi_exec($this->handle, $active);
    }

    /**
     * @return int[]|false
     */
    public function curlMultiInfoRead(int &$queuedMessages)
    {
        return $queuedMessages == -1 ? curl_multi_info_read($this->handle) : curl_multi_info_read($this->handle, $queuedMessages);
    }

    /**
     * @param int $errorCode
     * @return string|null
     */
    public static function curlMultiStrError(int $errorCode)
    {
        return curl_multi_strerror($errorCode);
    }
}