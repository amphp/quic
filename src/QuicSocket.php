<?php

namespace Amp\Quic;

use Amp\Socket\Socket;
use Amp\Socket\TlsInfo;

/**
 * A QuicSocket represents a QUIC Stream.
 * It's the terminal representation of incoming data.
 */
interface QuicSocket extends Socket
{
    /** @return int The id of the stream.  */
    public function getId(): int;

    /**
     * Half-closes the reading end of the stream, the counterpart to WritableStream::end().
     * @param int $errorcode Optional Application Protocol Error Code
     */
    public function endReceiving(int $errorcode = 0): void;

    /**
     * @inheritdoc
     * Pending writes and reads are immediately aborted.
     * @param int $errorcode Optional Application Protocol Error Code
     */
    public function close(int $errorcode = 0): void;

    /**
     * Forcefully closes the sending end of the stream, discards local outstanding writes on
     * this stream and allows the receiving end to discard any outstanding data on this stream.
     *
     * @param int $errorcode Optional Application Protocol Error Code
     * @throws \Amp\ByteStream\ClosedException If the stream has already been closed.
     */
    public function resetSending(int $errorcode = 0): void;

    /**
     * @param positive-int $chunkSize Used for reading and writing streamed data.
     */
    public function setChunkSize(int $chunkSize): void;

    /**
     * @return QuicConnection Holds the underlying connection
     */
    public function getConnection(): QuicConnection;

    /**
     * Sets the priority. Streams have a default priority of 127 and are sent interleaved.
     * Streams with a lower priority are sent first.
     * Sending of streams with equal priority may
     * The priority is handled as described by RFC 9218. While that RFC applies to HTTP, we'll take the same considerations.
     *
     * @param int $priority A number capped between 0 and 255.
     * @param bool $incremental Whether outstanding data on the stream should be interleaved, or rather fully sent at once.
     */
    public function setPriority(int $priority = 127, bool $incremental = true): void;

    /**
     * @inheritDoc
     *
     * @return TlsInfo The TLS (crypto) of the connection.
     */
    public function getTlsInfo(): TlsInfo;

    /**
     * @return bool Whether the stream was terminated with a RESET_STREAM.
     */
    public function wasReset(): bool;

    // TODO getCloseReason(): int; - it's not implemented upstream yet: https://github.com/cloudflare/quiche/issues/1699
}
