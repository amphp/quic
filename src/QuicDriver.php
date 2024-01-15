<?php

namespace Amp\Quic;

use Amp\Cancellation;
use Amp\Socket\InternetAddress;

abstract class QuicDriver
{
    private static ?QuicDriver $driver;

    public static function set(QuicDriver $driver): void
    {
        self::$driver = $driver;
    }

    public static function get(): QuicDriver
    {
        return self::$driver ??= new QuicheDriver();
    }

    /**
     * @param InternetAddress $address The address to connect to.
     *
     * @return QuicConnection Connection established!
     */
    abstract public function connect(
        InternetAddress $address,
        QuicClientConfig $config,
        ?Cancellation $cancellation = null,
    ): QuicConnection;

    /**
     * @param non-empty-array<InternetAddress> $addresses All addresses the server may be bound to.
     *
     * @return QuicServerSocket Ready to receive connections.
     */
    abstract public function bind(array $addresses, QuicServerConfig $config): QuicServerSocket;
}
