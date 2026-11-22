<?php

namespace App\Services\Ai;

use RuntimeException;

/** Pesan untuk pengguna saat AI tidak dapat dipakai. Core ticketing tidak terpengaruh. */
class AiUnavailableException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 503)
    {
        parent::__construct($message);
    }
}
