<?php

namespace Amp\Quic;

class QuicConnectionError
{
    public function __construct(public ?QuicError $error, public int $code, public string $reason)
    {
    }
}
