<?php declare(strict_types=1);

namespace Amp\Quic\Quiche;

final class QuicheStats
{
    public function __construct(
        /** The number of QUIC packets received. */
        public readonly int $recv,
        /** The number of QUIC packets sent. */
        public readonly int $sent,
        /** The number of QUIC packets that were lost. */
        public readonly int $lost,
        /** The number of sent QUIC packets with retransmitted data. */
        public readonly int $retrans,
        /** The number of sent bytes. */
        public readonly int $sentBytes,
        /** The number of received bytes. */
        public readonly int $recvBytes,
        /** The number of bytes sent lost. */
        public readonly int $lostBytes,
        /** The number of stream bytes retransmitted. */
        public readonly int $streamRetransBytes,
        /** @var list<QuichePathStats> The number of known paths for the connection. */
        public readonly array $paths,
        /** The number of streams reset by local. */
        public readonly int $resetStreamCountLocal,
        /** The number of streams stopped by local. */
        public readonly int $stoppedStreamCountLocal,
        /** The number of streams reset by remote. */
        public readonly int $resetStreamCountRemote,
        /** The number of streams stopped by remote. */
        public readonly int $stoppedStreamCountRemote,
    ) {
    }
}
