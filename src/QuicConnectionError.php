<?php declare(strict_types=1);

namespace Amp\Quic;

final class QuicConnectionError
{
    public function __construct(
        public readonly ?QuicError $error,
        public readonly int $code,
        public readonly string $reason,
    ) {
    }
}
