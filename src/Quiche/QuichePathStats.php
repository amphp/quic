<?php

namespace Amp\Quic\Quiche;

use Amp\Socket\InternetAddress;

class QuichePathStats
{
    /** The local address of the path. */
    public InternetAddress $localAddr;
    /** The peer address of the path. */
    public InternetAddress $peerAddr;
    /** The path validation state. */
    public int $validationState;
    /** Whether the path is marked as active. */
    public bool $active;
    /** The number of QUIC packets received. */
    public int $recv;
    /** The number of QUIC packets sent. */
    public int $sent;
    /** The number of QUIC packets that were lost. */
    public int $lost;
    /** The number of sent QUIC packets with retransmitted data. */
    public int $retrans;
    /** The estimated round-trip time of the connection in seconds. */
    public float $rtt;
    /** The size of the connection’s congestion window in bytes. */
    public int $cwnd;
    /** The number of sent bytes. */
    public int $sentBytes;
    /** The number of received bytes. */
    public int $recvBytes;
    /** The number of bytes lost. */
    public int $lostBytes;
    /** The number of stream bytes retransmitted. */
    public int $streamRetransBytes;
    /** The current PMTU for the connection. */
    public int $pmtu;
    /** The most recent data delivery rate estimate in bytes/s. */
    public int $deliveryRate;
}
