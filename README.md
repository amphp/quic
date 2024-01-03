# amphp/quic

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind. `amphp/quic` is a library for establishing quic connections. It provides a socket abstraction for clients and servers. It abstracts the really low levels of the [cloudflare/quiche](https://github.com/cloudflare/quiche) library.

[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](https://github.com/amphp/quic/blob/master/LICENSE)

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/quic
```

## Requirements

`amphp/quic` implements the basic Server and Client interfaces of `amphp/socket`. It requires the FFI extension to be installed.

## TLS

Given that QUIC requires TLS, it is required to have a valid certificate on the server side. Also, one or more application layer protocol names (ALPN) MUST be specified.

_Note_: Fingerprinting is not supported and ignored. To use a self-signed certificate, disabling peer verification is required, or having a proper custom root CA.

## Connecting to a Server

The simplest way to connect is just a target and an ALPN protocol.

```php
$quicConnection = \Amp\Quic\connect("example.com:1234", ["alpn protocol"]);
```

### Handling connections

Every QUIC connection is multiplexing streams. `QuicConnection` allows creating and accepting new streams.

Use `accept()` to await a new stream (`QuicSocket`), similarly to `Amp\Socket\ServerSocket`. Create new streams with `openStream()`. Also, datagrams can be sent and received on the connection with `sendDatagram()` and `receiveDatagram()`.

QUIC streams implement `Amp\Socket\Socket` for sending and receiving data.

Shutting down a connection via `close()` (or losing all references to it) immediately terminates all streams. 

## Server

`amphp/quic` allows listening for incoming QUIC connections over UDP. As with the client, it requires an ALPN protocol. Also a valid TLS certificate is required.

```php
$tls = (new ServerTlsContext)
    ->withDefaultCertificate(new Certificate(__DIR__ . "/cert.pem", __DIR__ . "/key.pem"))
    ->withApplicationLayerProtocols(["test"]);

$quicServerSocket = \Amp\Quic\bind(["0.0.0.0:1234", "[::]:1234"], $tls);
```

_Note_: SNI based certificate distinction is not supported. There must be exactly one certificate provided via `ServerTlsContext::withDefaultCertificate()`.

### Accepting connections

It's possible to `accept()` on a `QuicServerSocket`, but this will directly return a `QuicSocket`. This mode of operation is recommended, when connections do not have a specific meaning and streams are all what you are intersted in.

To actually get a `QuicConnection`, use `acceptConnection()`:

```php
while ($quicConnection = $quicServerSocket->acceptConnection()) {
    async(function() use ($quicConnection) {
        // handle the connection, open and accept new streams, send and receive datagrams
    })
}
```

## Configuration

While most configuration of `Amp\Socket\ConnectContext` and `Amp\Socket\BindContext` is understood and applied, there is also QUIC specific configuration exposed by `QuicConfig` and the respective child classes `QuicClientConfig` and `QuicServerConfig` which are both accepted by the `connect()` and `bind()` functions respectively.

Note that the QUIC interface generally accepts all sorts of communication, i.e. unidirectional streams, bidirectional streams and datagrams, as well as having an idle timeout for timely connection timeouts, with a ping being sent every so often to prevent timeouts on live connections. In particular QUIC servers may desire to tweak these settings.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
