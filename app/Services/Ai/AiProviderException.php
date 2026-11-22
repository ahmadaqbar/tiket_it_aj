<?php

namespace App\Services\Ai;

use RuntimeException;

/** Error dari provider. $errorCode aman untuk dicatat (tidak memuat API key/isi prompt). */
class AiProviderException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $errorCode);
    }
}
