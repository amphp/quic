<?php

namespace Amp\Quic;

use Amp\ByteStream\ClosedException;
use Amp\Cancellation;
use Amp\Socket\InternetAddress;
use Amp\Socket\ServerSocket;
use Amp\Socket\TlsInfo;
use Amp\Socket\UdpSocket;

/**
 * A QUIC connection can create streams as well as send datagrams over it.
 * It can be used both on the server side via {@see QuicServerSocket::acceptConnection()} and the client side via {@see QuicDriver::connect()}.
 * On the server side its lifetime is not bound to the server. However, all streams created by this connection are bound to the lifetime of the connection itself.
 */
interface QuicConnection extends UdpSocket, ServerSocket
{
    /**
     * @inheritDoc
     * Closing the quic connection will terminate all pending streams immediately.
     *
     * @param int|QuicError $error An integer error is an application error. To send a QUIC error, use the QuicError enum.
     * @param string $reason An arbitrary error reason.
     */
    public function close(int|QuicError $error = QuicError::NO_ERROR, string $reason = ""): void;

    /**
     * @return InternetAddress The address of the peer.
     */
    public function getRemoteAddress(): InternetAddress;

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
     * @return InternetAddress The local address.
     */
    public function getAddress(): InternetAddress;

    /**
     * @inheritDoc
     * @param InternetAddress|null $address The address is completely ignored and discarded.
     */
    public function send(?InternetAddress $address, string $data): void;

    /**
     * The same as {@see receive}, but without returned InternetAddress.
     *
     * @param positive-int|null $limit Discard any bytes past $limit bytes from the datagram.
     * Specifying {@code null} returns the full message.
     * @return string|null Returns {@code null} if the socket is closed.
     */
    public function receiveDatagram(?Cancellation $cancellation = null, ?int $limit = null): ?string;

    /**
     * Attempts sending a datagram over the connection.
     *
     * @param string $data The data to send. It MUST be smaller than {@see maxDatagramSize()}.
     * @throws ClosedException If the connection was closed.
     * @return bool Whether the datagram send buffer was full.
     */
    public function trySendDatagram(string $data): bool;

    /**
     * Sends a datagram over the connection with backpressure. The backpressure is determined by {@see QuicConfig::withDatagramSendQueueSize()}.
     *
     * @param string $data The data to send. It MUST be smaller than {@see maxDatagramSize()}.
     * @param Cancellation|null $cancellation Abort waiting to send.
     * @throws ClosedException If the connection was closed.
     */
    public function sendDatagram(string $data, ?Cancellation $cancellation = null): void;

    /**
     * @return int The maximum size a single datagram may have.
     */
    public function maxDatagramSize(): int;

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
     * @return QuicConnectionError A connection error may not have been made available, e.g. on timeout. It will be NO_ERROR with a $code == -1 then.
     */
    public function getCloseReason(): QuicConnectionError;

    /**
     * Provides an implementation specific way to expose statistics about the connection.
     */
    public function stats();
}
