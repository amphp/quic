<?php declare(strict_types=1);

namespace Amp\Quic\Quiche;

use Amp\Socket\InternetAddress;

final class QuichePathStats
{
    public function __construct(
        /** The local address of the path. */
        public readonly InternetAddress $localAddr,
        /** The peer address of the path. */
        public readonly InternetAddress $peerAddr,
        /** The path validation state. */
        public readonly int $validationState,
        /** Whether the path is marked as active. */
        public readonly bool $active,
        /** The number of QUIC packets received. */
        public readonly int $recv,
        /** The number of QUIC packets sent. */
        public readonly int $sent,
        /** The number of QUIC packets that were lost. */
        public readonly int $lost,
        /** The number of sent QUIC packets with retransmitted data. */
        public readonly int $retrans,
        /** The estimated round-trip time of the connection in seconds. */
        public readonly float $rtt,
        /** The size of the connection’s congestion window in bytes. */
        public readonly int $cwnd,
        /** The number of sent bytes. */
        public readonly int $sentBytes,
        /** The number of received bytes. */
        public readonly int $recvBytes,
        /** The number of bytes lost. */
        public readonly int $lostBytes,
        /** The number of stream bytes retransmitted. */
        public readonly int $streamRetransBytes,
        /** The current PMTU for the connection. */
        public readonly int $pmtu,
        /** The most recent data delivery rate estimate in bytes/s. */
        public readonly int $deliveryRate,
    ) {
    }
}
