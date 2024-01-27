<?php declare(strict_types=1);

namespace Amp\Quic;

use Amp\Socket\InternetAddress;

interface UdpClient extends DatagramStream
{
    /**
     * @return InternetAddress The local address.
     */
    public function getLocalAddress(): InternetAddress;

    /**
     * @return InternetAddress The address of the peer.
     */
    public function getRemoteAddress(): InternetAddress;
}
