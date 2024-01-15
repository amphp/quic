<?php

namespace Amp\Quic;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\ResourceStream;
use Amp\Cancellation;
use Amp\Socket\InternetAddress;
use Amp\Socket\ServerSocket;
use Amp\Socket\SocketException;
use Amp\Socket\TlsInfo;
use Amp\Socket\TlsState;

// TODO: Find a common interface for everything Socket-like in amp/socket, without the Readable&WritableStream interfaces.

/**
 * A QUIC connection can create streams as well as send datagrams over it.
 * It can be used both on the server side via {@see QuicServerSocket::acceptConnection()} and the client side via
 * {@see QuicDriver::connect()}. On the server side its lifetime is not bound to the server. However, all streams
 * created by this connection are bound to the lifetime of the connection itself.
 */
interface QuicConnection extends UdpClient, ServerSocket, ResourceStream
{
    /**
     * @inheritDoc
     * Closing the quic connection will terminate all pending streams immediately.
     *
     * @param int|QuicError $error An integer error is an application error. To send a QUIC error, use the QuicError
     *     enum.
     * @param string $reason An arbitrary error reason.
     */
    public function close(int|QuicError $error = QuicError::NO_ERROR, string $reason = ""): void;

    /**
     * @inheritDoc
     *
     * @return QuicSocket|null Null if the connection was closed, otherwise the next stream.
     */
    public function accept(?Cancellation $cancellation = null): ?QuicSocket;

    /**
     * This allocates a new ID for a QUIC stream.
     *
     * @return QuicSocket A new QUIC stream.
     */
    public function openStream(): QuicSocket;

    /**
     * Implementing ServerSocket interface, forwards to {@see getLocalAddress}.
     *
     * @return InternetAddress The local address.
     */
    public function getAddress(): InternetAddress;

    public function receive(?Cancellation $cancellation = null): ?string;

    /**
     * Attempts sending a datagram over the connection.
     *
     * @param string $data The data to send. It MUST be smaller than {@see maxDatagramSize()}.
     *
     * @return bool Whether the datagram send buffer was full.
     * @throws ClosedException If the connection was closed.
     */
    public function trySend(string $data): bool;

    /**
     * Sends a datagram over the connection with backpressure. The backpressure is determined by
     * {@see QuicConfig::withDatagramSendQueueSize()}.
     *
     * @param string $data The data to send. It MUST be smaller than {@see maxDatagramSize()}.
     * @param Cancellation|null $cancellation Abort waiting to send.
     *
     * @throws ClosedException If the connection was closed.
     */
    public function send(string $data, ?Cancellation $cancellation = null): void;

    /**
     * No-op, QUIC connections always have TLS active.
     */
    public function setupTls(?Cancellation $cancellation = null): void;

    /**
     * @throws SocketException Cannot be shutdown.
     */
    public function shutdownTls(?Cancellation $cancellation = null): void;

    /**
     * @return true Always true.
     */
    public function isTlsConfigurationAvailable(): bool;

    /**
     * @return TlsState Effectively always TlsState::Enabled
     */
    public function getTlsState(): TlsState;

    /**
     * @return TlsInfo The TLS state of the connection.
     */
    public function getTlsInfo(): TlsInfo;

    /**
     * Resets the idle timeout if successful.
     * Implemented by sending an ack-eclicting frame or a PING frame.
     */
    public function ping(): void;

    /**
     * Must not be called before the connection is known to be closed.
     *
     * @return QuicConnectionError A connection error may not have been made available, e.g. on timeout. It will be
     *     NO_ERROR with a $code == -1 then.
     */
    public function getCloseReason(): QuicConnectionError;

    /**
     * Retrieves an open stream by its id.
     *
     * @return QuicSocket|null The stream or {@code null} if not found.
     */
    public function getStream(int $id): ?QuicSocket;

    /**
     * Provides an implementation specific way to expose statistics about the connection.
     *
     * @psalm-suppress MissingReturnType
     */
    public function stats() /* : implementation defined */ ;
}
