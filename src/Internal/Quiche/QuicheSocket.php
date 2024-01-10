<?php

namespace Amp\Quic\Internal\Quiche;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\Quic\Bindings\Quiche;
use Amp\Quic\Bindings\uint8_t_ptr;
use Amp\Quic\QuicConnection;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Socket\TlsState;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

final class QuicheSocket implements \Amp\Quic\QuicSocket, \IteratorAggregate
{
    use ReadableStreamIteratorAggregate;

    public const DEFAULT_CHUNK_SIZE = ReadableResourceStream::DEFAULT_CHUNK_SIZE;

    private bool $referenced = true;
    private int $chunkSize = self::DEFAULT_CHUNK_SIZE;
    public int $closed = 0;
    public ?DeferredFuture $onClose = null;

    public const UNREADABLE = 2;
    public const UNWRITABLE = 1;
    public const CLOSED = self::UNWRITABLE | self::UNREADABLE;

    private static uint8_t_ptr $buffer;
    private static int $bufferSize = 0;

    private int $currentReadSize;
    public bool $readPending = false;
    private bool $eofReached = false;
    private bool $wasReset = false;
    public ?Suspension $reader = null;
    private readonly \Closure $cancel;

    /** @var \SplQueue<array{string, Suspension|null}> */
    public \SplQueue $writes;

    public int $id;
    public int $priority;
    public bool $incremental;

    public function __construct(private QuicheConnection $connection, int $id = null)
    {
        $suspension = &$this->reader;
        $this->cancel = static function (CancelledException $exception) use (&$suspension): void {
            $suspension?->throw($exception);
            $suspension = null;
        };
        $this->writes = new \SplQueue;
        if (isset($id)) {
            $this->id = $id;
            if ($id & 2) { // uni-directional stream
                $this->closed = self::UNWRITABLE;
            }
        }
    }

    public function getId(): int
    {
        if (!isset($this->id)) {
            $this->connection->allocStreamId($this);
        }
        return $this->id;
    }

    private function ensureBufferSize(int $size): void
    {
        if ($size > self::$bufferSize) {
            self::$buffer = uint8_t_ptr::array($size);
            self::$bufferSize = $size;
        }
    }

    public function setupTls(?Cancellation $cancellation = null): void
    {
        // Nothing to do here, QuicSockets are always encrypted.
    }

    public function shutdownTls(?Cancellation $cancellation = null): void
    {
        if ($this->closed === self::CLOSED) {
            throw new ClosedException("Can't shutdown TLS, because the socket has already been closed");
        }

        throw new StreamException("Cannot disable TLS on a QUIC connection");
    }

    public function read(?Cancellation $cancellation = null, ?int $limit = null): ?string
    {
        if ($limit === null) {
            $limit = $this->chunkSize;
        } elseif ($limit <= 0) {
            throw new \ValueError('The length limit must be a positive integer, got ' . $limit);
        }

        if ($this->reader !== null) {
            throw new PendingReadError;
        }

        if (($this->closed & self::UNREADABLE) || !isset($this->connection->connection)) {
            return null; // Return null on closed stream.
        }

        $this->ensureBufferSize($limit);
        $this->currentReadSize = $limit;

        if ($this->readPending) {
            if (null !== $read = $this->doRead()) {
                return $read;
            }
            if ($this->eofReached) {
                return null;
            }
        } elseif (!isset($this->id)) {
            $this->connection->allocStreamId($this);
        }

        $id = $cancellation?->subscribe($this->cancel);

        $this->reader = EventLoop::getSuspension();
        if ($this->referenced) {
            $this->connection->state->reference();
        }

        try {
            return $this->reader->suspend();
        } finally {
            if ($this->referenced) {
                $this->connection->state->unreference();
            }

            /** @psalm-suppress PossiblyNullArgument $id is always defined if $cancellation is non-null */
            $cancellation?->unsubscribe($id);
        }
    }

    private function writeChunk(string $bytes, bool $fin = false): int
    {
        $written = QuicheState::$quiche->quiche_conn_stream_send($this->connection->connection, $this->id, $bytes, \strlen($bytes), $fin);
        if ($written >= 0) {
            $this->connection->state->checkSend($this->connection);
        }
        return $written;
    }

    public function write(string $bytes): void
    {
        if ($this->closed & self::UNWRITABLE) {
            throw new ClosedException("The stream was closed");
        }

        // We'll allow initiating a stream with 0 bytes
        if ($bytes === "" && $this->writes->isEmpty() && isset($this->id)) {
            return;
        }

        if (!$this->writes->isEmpty()) {
            $chunks = \str_split($bytes, $this->chunkSize);
            $lastChunk = \array_pop($chunks);
            foreach ($chunks as $chunk) {
                $this->writes->push([$chunk, null]);
            }
            $this->writes->push([$lastChunk, $suspension = EventLoop::getSuspension()]);
        } else {
            if (!isset($this->id)) {
                if (!$this->connection->connection) {
                    throw new ClosedException("The stream was closed");
                }

                $this->connection->allocStreamId($this);
            }

            $size = \strlen($bytes);
            if ($size < $this->chunkSize) {
                $written = $this->writeChunk($bytes);
                if ($written === $size) {
                    return;
                }
                $i = 0;
                if ($written >= 0) {
                    $chunks = [\substr($bytes, $written)];
                } else {
                    $chunks = [$bytes];
                }
            } else {
                $chunks = \str_split($bytes, $this->chunkSize);
                foreach ($chunks as $i => $chunk) {
                    $written = $this->writeChunk($chunk);
                    if ($written !== \strlen($chunk)) {
                        if ($written >= 0) {
                            $chunks[$i] = \substr($bytes, $written);
                        }
                        goto chunk_error;
                    }
                }
                return;
            }

            chunk_error:
            if ($written < 0) {
                if ($written === Quiche::QUICHE_ERR_DONE) {
                    if (QuicheState::$quiche->quiche_conn_stream_writable($this->connection->connection, $this->id, 0) === Quiche::QUICHE_ERR_INVALID_STREAM_STATE) {
                        throw new ClosedException("Could not write to a stream closed by the peer");
                    }
                } else {
                    if ($written === Quiche::QUICHE_ERR_STREAM_STOPPED) {
                        throw new ClosedException("Could not write to a stream closed by the peer");
                    }
                    throw new StreamException("Could not write to QUIC stream {$this->id} (error: $written)");
                }
            }

            for ($count = \count($chunks) - 1; $i < $count; ++$i) {
                $this->writes->push([$chunks[$i], null]);
            }
            $this->writes->push([$chunks[$i], $suspension = EventLoop::getSuspension()]);
        }

        if ($err = $suspension->suspend()) {
            $err();
        }
    }

    public function end(): void
    {
        if ($this->closed & self::UNWRITABLE) {
            return;
        }

        if ($this->writes->isEmpty() && isset($this->id)) {
            QuicheState::$quiche->quiche_conn_stream_send($this->connection->connection, $this->id, null, 0, true);
            $this->connection->state->checkSend($this->connection);
        }

        $this->closed |= self::UNWRITABLE;
    }

    public function resetSending(int $errorcode = 0): void
    {
        $this->connection->shutdownStream($this, true, $errorcode);
    }

    public function endReceiving(int $errorcode = 0): void
    {
        if ($errorcode && !isset($this->id)) {
            $this->connection->allocStreamId($this);
        }
        $this->connection->shutdownStream($this, false, $errorcode);
    }

    public function close(int $errorcode = 0): void
    {
        $this->connection->closeStream($this, $errorcode, true);
    }

    public function reference(): void
    {
        if (!$this->referenced) {
            $this->referenced = true;
            if ($this->reader) {
                $this->connection->state->reference();
            }
        }
    }

    public function unreference(): void
    {
        if ($this->referenced) {
            $this->referenced = false;
            if ($this->reader) {
                $this->connection->state->unreference();
            }
        }
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->connection->localAddress;
    }

    /**
     * @return resource
     */
    public function getResource()
    {
        return $this->connection->socket;
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->connection->address;
    }

    public function getTlsState(): TlsState
    {
        return TlsState::Enabled;
    }

    public function getTlsInfo(): TlsInfo
    {
        return $this->connection->getTlsInfo();
    }

    public function isTlsConfigurationAvailable(): bool
    {
        return true;
    }

    public function wasReset(): bool
    {
        return $this->wasReset;
    }

    public function isClosed(): bool
    {
        return $this->closed === self::CLOSED;
    }

    public function onClose(\Closure $onClose): void
    {
        ($this->onClose ??= new DeferredFuture)->getFuture()->finally($onClose);
    }

    /**
     * @param positive-int $chunkSize New default chunk size for reading and writing.
     */
    public function setChunkSize(int $chunkSize): void
    {
        if ($chunkSize <= 0) {
            throw new \ValueError('The chunk length must be a positive integer');
        }

        $this->chunkSize = $chunkSize;
    }

    public function isReadable(): bool
    {
        return ($this->closed & self::UNREADABLE) === 0;
    }

    public function isWritable(): bool
    {
        return ($this->closed & self::UNWRITABLE) === 0;
    }

    public function getConnection(): QuicConnection
    {
        return $this->connection;
    }

    public function setPriority(int $priority = 127, bool $incremental = true): void
    {
        if ($this->closed & self::UNREADABLE) {
            return;
        }

        if (isset($this->id)) {
            QuicheState::$quiche->quiche_conn_stream_priority($this->connection->connection, $this->id, $priority, $incremental);
        }

        $this->priority = $priority;
        $this->incremental = $incremental;
    }

    private function doRead(): ?string
    {
        if ($this->eofReached) {
            close:
            if (!($this->closed & self::UNREADABLE)) {
                $this->closed |= self::UNREADABLE;
                $this->onClose?->complete();
            }
            return null;
        }

        $received = QuicheState::$quiche->quiche_conn_stream_recv($this->connection->connection, $this->id, self::$buffer, $this->currentReadSize, [&$fin]);
        $this->connection->state->checkSend($this->connection);
        if ($received >= -1) {
            $this->eofReached = $fin;
            if ($received > 0) {
                return self::$buffer->toString($received);
            }
            if ($fin) {
                goto close;
            }
            $this->readPending = false;
            return null;
        }

        if ($received === Quiche::QUICHE_ERR_STREAM_RESET || $received === Quiche::QUICHE_ERR_INVALID_STREAM_STATE) {
            $this->wasReset = true;
            $this->eofReached = true;
            goto close;
        }

        throw new StreamException("Failed to read on stream {$this->id}: error $received");
    }

    public function notifyReadable(): void
    {
        if ($this->reader) {
            if ((null !== $buf = $this->doRead()) || $this->eofReached) {
                $this->reader->resume($buf);
                $this->reader = null;
            }
        }
        $this->readPending = true;
    }

    public function notifyWritable(): void
    {
        $empty = $this->writes->isEmpty();
        while (!$empty) {
            [$data, $suspension] = $this->writes->shift();
            $empty = $this->writes->isEmpty();
            $length = \strlen($data);

            if ($length === 0 && (!$empty || ($this->closed & self::UNWRITABLE))) {
                $suspension?->resume();
                continue;
            }

            $written = $this->writeChunk($data, $empty && ($this->closed & self::UNWRITABLE));

            if ($written !== $length) {
                if ($written < 0) {
                    if ($written === Quiche::QUICHE_ERR_DONE) {
                        $this->writes->unshift([$data, $suspension]);
                        return;
                    }
                    $id = $this->id;
                    $suspension?->resume(static function () use ($id, $written) {
                        if ($written === Quiche::QUICHE_ERR_STREAM_RESET || $written === Quiche::QUICHE_ERR_STREAM_STOPPED) {
                            throw new ClosedException("Could not write to a stream $id by the peer");
                        }
                        throw new StreamException("Could not write to QUIC stream $id (error: $written)");
                    });
                } else {
                    $data = \substr($data, $written);
                    $this->writes->unshift([$data, $suspension]);
                    return;
                }
            } else {
                $suspension?->resume();
            }
        }
    }

    public function __destruct()
    {
        $this->connection->closeStream($this);
    }
}
