<?php

namespace Amp\Quic\Quiche;

class QuicheStats
{
    /** The number of QUIC packets received. */
    public int $recv;
    /** The number of QUIC packets sent. */
    public int $sent;
    /** The number of QUIC packets that were lost. */
    public int $lost;
    /** The number of sent QUIC packets with retransmitted data. */
    public int $retrans;
    /** The number of sent bytes. */
    public int $sentBytes;
    /** The number of received bytes. */
    public int $recvBytes;
    /** The number of bytes sent lost. */
    public int $lostBytes;
    /** The number of stream bytes retransmitted. */
    public int $streamRetransBytes;
    /** @var QuichePathStats[] The number of known paths for the connection. */
    public array $paths;
    /** The number of streams reset by local. */
    public int $resetStreamCountLocal;
    /** The number of streams stopped by local. */
    public int $stoppedStreamCountLocal;
    /** The number of streams reset by remote. */
    public int $resetStreamCountRemote;
    /** The number of streams stopped by remote. */
    public int $stoppedStreamCountRemote;
}
