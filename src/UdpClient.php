<?php declare(strict_types=1);

namespace Amp\Quic;

use Amp\Socket\SocketAddress;

interface UdpClient extends DatagramStream
{
    /**
     * @return SocketAddress The local address.
     */
    public function getLocalAddress(): SocketAddress;

    /**
     * @return SocketAddress The address of the peer.
     */
    public function getRemoteAddress(): SocketAddress;
}
