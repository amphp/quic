<?php

namespace Amp\Quic;

use Amp\Cancellation;
use Amp\Socket\ConnectException;
use Amp\Socket\InternetAddress;
use Amp\Socket\SocketException;

class QuicheDriver extends QuicDriver
{
    public function connect(InternetAddress $address, QuicClientConfig $config, ?Cancellation $cancellation = null): QuicConnection
    {
        $uri = "udp://{$address->toString()}";
        $streamContext = \stream_context_create($config->toStreamContextArray());

        \set_error_handler(fn ($errno, $errstr) => throw new ConnectException("Connection to $uri failed: (Error #$errno) $errstr", $errno));

        try {
            $socket = \stream_socket_client($uri, $errno, $errstr, null, \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT, $streamContext);
        } finally {
            \restore_error_handler();
        }

        if (!$socket || $errno) {
            throw new SocketException(
                \sprintf('Could not create datagram client %s: [Error: #%d] %s', $uri, $errno, $errstr),
                $errno
            );
        }

        \stream_set_blocking($socket, false);

        return Internal\Quiche\QuicheClientState::connect(
            $config->getHostname() ?? $address->getAddress(),
            $socket,
            $config,
            $cancellation,
        );
    }

    public function bind(array $addresses, QuicServerConfig $config): QuicServerSocket
    {
        $servers = [];
        foreach ($addresses as $address) {
            $uri = "udp://{$address->toString()}";
            $streamContext = \stream_context_create($config->toStreamContextArray());

            // Error reporting suppressed since stream_socket_server() emits an E_WARNING on failure (checked below).
            $server = @\stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND, $streamContext);

            if (!$server || $errno) {
                throw new SocketException(
                    \sprintf('Could not create datagram socket %s: [Error: #%d] %s', $uri, $errno, $errstr),
                    $errno
                );
            }

            $servers[] = $server;
        }

        return new Internal\Quiche\QuicheServerSocket($servers, $config);
    }
}
