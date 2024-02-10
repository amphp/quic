<?php declare(strict_types=1);

namespace Amp\Quic\Pair;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Quic\QuicConnection;
use Amp\Quic\QuicSocket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Socket\TlsState;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

/**
 * @implements \IteratorAggregate<int, string>
 */
class PairSocket implements QuicSocket, \IteratorAggregate
{
    use ReadableStreamIteratorAggregate;

    public const UNREADABLE = 2;
    public const UNWRITABLE = 1;
    public const CLOSED = self::UNWRITABLE | self::UNREADABLE;

    public int $closed = 0;
    /** @var positive-int */
    private int $chunkSize = 16384;
    private bool $wasReset = false;
    private readonly DeferredFuture $onClose;

    private ?int $id = null;

    private int $writeSizeBuffer = -1;
    private array $readQueue = [];
    private ?Suspension $readSuspension = null;
    /** @var Suspension[] */
    private array $writeSuspensions = [];

    /** @psalm-suppress PropertyNotSetInConstructor */
    public PairSocket $other;

    public int $priority = 127;
    public bool $incremental = true;

    public function __construct(private PairConnection $connection)
    {
        $this->onClose = new DeferredFuture;
    }

    public function isClosed(): bool
    {
        return $this->closed === self::CLOSED;
    }

    public function onClose(\Closure $onClose): void
    {
        $this->onClose->getFuture()->finally($onClose);
    }

    public function getId(): int
    {
        return $this->id ??= $this->connection->allocStreamId($this);
    }

    private function ensureSocketSent(): void
    {
        $this->getId();
        if (!isset($this->other->id)) {
            $this->other->id = $this->id;
            $this->connection->sendSocket($this->other);
        }
    }

    public function endReceiving(int $errorcode = 0): void
    {
        if ($errorcode && $this->id === null && ($this->closed & self::UNREADABLE) === 0) {
            $this->ensureSocketSent();
        }

        $this->other->resetSending($errorcode);
    }

    public function close(int $errorcode = 0): void
    {
        if ($this->closed === self::CLOSED) {
            return;
        }

        $this->forceUnwritable();
        $this->other->forceUnwritable();

        $this->checkActuallyClosed();
        $this->other->checkActuallyClosed();

        $this->collectPendingWrites();
        $this->other->collectPendingWrites();
    }

    public function resetSending(int $errorcode = 0): void
    {
        if ($this->closed & self::UNWRITABLE) {
            return;
        }

        $this->forceUnwritable();

        $this->checkActuallyClosed();
        $this->other->checkActuallyClosed();

        $this->collectPendingWrites();
    }

    private function forceUnwritable(): void {
        $this->closed |= self::UNWRITABLE;

        $this->other->readQueue = [];
        $this->other->closed |= self::UNREADABLE;
        $this->other->readSuspension?->resume();
        $this->other->readSuspension = null;
        $this->other->wasReset = true;
    }

    public function setChunkSize(int $chunkSize): void
    {
        /** @psalm-suppress DocblockTypeContradiction */
        if ($chunkSize <= 0) {
            throw new \ValueError('The chunk length must be a positive integer');
        }

        $this->chunkSize = $chunkSize;
    }

    public function getConnection(): QuicConnection
    {
        return $this->connection;
    }

    public function setPriority(int $priority = 127, bool $incremental = true): void
    {
        $this->priority = $priority;
        $this->incremental = $incremental;
    }

    public function getTlsInfo(): TlsInfo
    {
        return $this->connection->getTlsInfo();
    }

    public function wasReset(): bool
    {
        return $this->wasReset;
    }

    public function isReadable(): bool
    {
        return ($this->closed & self::UNREADABLE) === 0;
    }

    public function read(?Cancellation $cancellation = null, ?int $limit = null): ?string
    {
        if ($this->readSuspension) {
            throw new PendingReadError;
        }

        if ($this->closed & self::UNREADABLE) {
            return null;
        }

        if ($this->other->writeSuspensions) {
            $suspension = \current($this->other->writeSuspensions);
            try {
                $suspension->resume();
            } catch (\Throwable) {
            }
        }

        if ($this->readQueue) {
            $val = \current($this->readQueue);
            unset($this->readQueue[\key($this->readQueue)]);
            $this->other->writeSizeBuffer += \strlen($val);
            return $val;
        }

        $this->readSuspension = EventLoop::getSuspension();
        $id = $cancellation?->subscribe($this->readSuspension->throw(...));
        try {
            return $this->readSuspension->suspend();
        } finally {
            /** @psalm-suppress PossiblyNullArgument https://github.com/vimeo/psalm/issues/10553 */
            $cancellation?->unsubscribe($id);
        }
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->connection->localAddress;
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->connection->remoteAddress;
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

    public function isTlsConfigurationAvailable(): bool
    {
        return true;
    }

    public function getTlsState(): TlsState
    {
        return TlsState::Enabled;
    }

    public function write(string $bytes): void
    {
        if ($this->closed & self::UNWRITABLE) {
            throw new ClosedException("The stream was closed");
        }

        $this->ensureSocketSent();

        if ($this->writeSuspensions) {
            $this->writeSuspensions[] = $suspension = EventLoop::getSuspension();
            try {
                $suspension->suspend();
            } finally {
                unset($this->writeSuspensions[\key($this->writeSuspensions)]);
            }
        }

        if ($this->writeSizeBuffer === -1) {
            $isUnidirectional = ($this->id % 4) & 2;
            if ($isUnidirectional) {
                $this->writeSizeBuffer = $this->connection->config->getMaxUnidirectionalData();
            } else {
                $this->writeSizeBuffer = \min($this->connection->config->getMaxLocalBidirectionalData(), $this->connection->config->getMaxRemoteBidirectionalData());
            }
        }

        $suspensionInserted = false;
        try {
            foreach (\str_split($bytes, $this->chunkSize) as $chunk) {
                while (true) {
                    if ($this->writeSizeBuffer < \strlen($chunk)) {
                        $immediateWrite = \substr($chunk, 0, $this->writeSizeBuffer);
                        $chunk = \substr($chunk, $this->writeSizeBuffer);
                        $this->directWrite($immediateWrite);

                        if (!$suspensionInserted) {
                            $suspensionInserted = true;
                            $this->writeSuspensions[] = $suspension = EventLoop::getSuspension();
                        }
                        /** @psalm-suppress PossiblyUndefinedVariable */
                        $suspension->suspend();
                    } else {
                        $this->directWrite($chunk);
                        break;
                    }
                }
            }
        } finally {
            if ($suspensionInserted) {
                unset($this->writeSuspensions[\key($this->writeSuspensions)]);
            }
        }
    }

    private function directWrite(string $chunk): void
    {
        if ($chunk !== "") {
            if ($this->other->readSuspension) {
                $this->other->readSuspension->resume($chunk);
                $this->other->readSuspension = null;
            } else {
                $this->writeSizeBuffer -= \strlen($chunk);
                $this->other->readQueue[] = $chunk;
            }
        }
    }

    public function end(): void
    {
        if ($this->closed & self::UNWRITABLE) {
            return;
        }

        $this->closed |= self::UNWRITABLE;
        $this->checkActuallyClosed();

        if (!isset($this->other->id)) {
            return;
        }

        EventLoop::queue(function () {
            while ($this->writeSuspensions || $this->other->readQueue) {
                $this->writeSuspensions[] = $suspension = EventLoop::getSuspension();
                try {
                    $suspension->suspend();
                } catch (ClosedException) {
                } finally {
                    unset($this->writeSuspensions[\key($this->writeSuspensions)]);
                }
            }

            $this->other->readSuspension?->resume();
            $this->other->readSuspension = null;
            $this->other->closed |= self::UNREADABLE;

            $this->other->checkActuallyClosed();
        });
    }

    public function isWritable(): bool
    {
        return ($this->closed & self::UNWRITABLE) === 0;
    }

    private function collectPendingWrites(): void
    {
        if ($this->writeSuspensions) {
            $exception = new ClosedException("The socket was closed before writing completed");
            foreach ($this->writeSuspensions as $suspension) {
                $suspension->throw($exception);
            }
        }
    }

    private function checkActuallyClosed(): void
    {
        if ($this->closed === self::CLOSED && !$this->onClose->isComplete()) {
            $this->connection->removeClosedStream($this);
            $this->onClose->complete();
        }
    }
}
