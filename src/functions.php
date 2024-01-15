<?php

namespace Amp\Quic;

use Amp\Cancellation;
use Amp\NullCancellation;
use Amp\Socket\BindContext;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\ConnectException;
use Amp\Socket\DnsSocketConnector;
use Amp\Socket\InternetAddress;
use Amp\Socket\ServerTlsContext;
use function Amp\Socket\Internal\parseUri;

/**
 * @param InternetAddress|string $address The address to connect to.
 * @param QuicClientConfig|ConnectContext|ClientTlsContext|string[] $protocolsOrConfig Configuration or just the ALPN
 *     protocols to connect to.
 *
 * @return QuicConnection The established connection.
 * @throws \Amp\Socket\SocketException If something goes wrong...
 */
function connect(
    InternetAddress|string $address,
    QuicClientConfig|ConnectContext|ClientTlsContext|array $protocolsOrConfig,
    ?Cancellation $cancellation = null,
): QuicConnection {
    $config = $protocolsOrConfig instanceof QuicClientConfig
        ? $protocolsOrConfig
        : new QuicClientConfig($protocolsOrConfig);

    if ($address instanceof InternetAddress) {
        return QuicDriver::get()->connect($address, $config);
    }

    if (!\str_contains($address, "://")) {
        $address = "udp://$address";
    }

    [$scheme, $host] = parseUri($address);

    if ($scheme !== 'udp') {
        throw new \Error('Only udp scheme allowed for QUIC socket creation');
    }

    if (null === $config->getHostname()) {
        $config = $config->withHostname($host);
    }

    /**
     * @psalm-var non-empty-array<string> $builtUris
     * @psalm-suppress InvalidScope https://github.com/vimeo/psalm/issues/913
     */
    $builtUris = (fn () => $this->resolve(
        $address,
        $config->getConnectContext(),
        $cancellation ?? new NullCancellation(),
    ))->call(new DnsSocketConnector());

    foreach ($builtUris as $builtUri) {
        try {
            $address = InternetAddress::fromString(\substr($builtUri, 6 /* remove leading udp:// */));
            return QuicDriver::get()->connect($address, $config, $cancellation);
        } catch (ConnectException $e) {
        }
    }

    throw $e;
}

/**
 * @param InternetAddress|string|non-empty-array<InternetAddress>|non-empty-array<string> $addresses
 *      The addresses to listen on. Connection migration between interfaces is supported.
 * @param QuicServerConfig|ServerTlsContext|BindContext $context Configuration, which MUST include a valid TLS setup.
 *
 * @return QuicServerSocket Ready to accept new connections.
 * @throws \Amp\Socket\SocketException If something goes wrong...
 */
function bind(
    InternetAddress|string|array $addresses,
    QuicServerConfig|ServerTlsContext|BindContext $context
): QuicServerSocket {
    if (!\is_array($addresses)) {
        $addresses = [$addresses];
    }

    /** @psalm-suppress TypeDoesNotContainType Manual check to enforce non-empty-array. */
    if (!$addresses) {
        throw new \Error("There must be at least one address to bind to");
    }

    $servers = [];
    foreach ($addresses as $address) {
        if (!($address instanceof InternetAddress)) {
            $scheme = \strstr($address, '://', true);
            $address = InternetAddress::fromString(match ($scheme) {
                'udp' => \substr($address, 6),
                false => $address,
                default => throw new \Error('Only udp scheme allowed for QUIC socket creation, found: ' . $address),
            });
        }

        $servers[] = $address;
    }

    if (!($context instanceof QuicServerConfig)) {
        $context = new QuicServerConfig($context);
    }

    return QuicDriver::get()->bind($servers, $context);
}
