<?php declare(strict_types=1);

namespace Amp\Quic\Pair;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\Quic\QuicClientConfig;
use Amp\Quic\QuicConfig;
use Amp\Quic\QuicConnection;
use Amp\Quic\QuicConnectionError;
use Amp\Quic\QuicError;
use Amp\Socket\BindContext;
use Amp\Socket\InternetAddress;
use Amp\Socket\PendingAcceptError;
use Amp\Socket\PendingReceiveError;
use Amp\Socket\ServerTlsContext;
use Amp\Socket\TlsInfo;
use Amp\Socket\TlsState;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

class PairConnection implements QuicConnection
{
    /** @psalm-return list{PairConnection, PairConnection} first server, then client */
    public static function createPair(QuicConfig|string $configOrApplicationLayerProtocol = ""): array
    {
        if (\is_string($configOrApplicationLayerProtocol)) {
            $configOrApplicationLayerProtocol = new QuicClientConfig([$configOrApplicationLayerProtocol]);
        }

        /** @psalm-suppress UnsafeInstantiation */
        $a = new static(true, $configOrApplicationLayerProtocol);
        /** @psalm-suppress UnsafeInstantiation */
        $b = new static(false, $configOrApplicationLayerProtocol);
        $a->other = $b;
        $b->other = $a;
        return [$a, $b];
    }

    /** @psalm-suppress PropertyNotSetInConstructor */
    private PairConnection $other;
    private bool $closed = false;
    private array $onClosed = [];
    private array $pendingAccepts = [];
    private ?Suspension $acceptSuspension = null;
    private ?QuicConnectionError $closeReason = null;

    public BindContext $bindContext;
    public int $maxDatagramSize = 1200;
    public InternetAddress $localAddress;
    public InternetAddress $remoteAddress;

    /** @var array<string> */
    private array $datagramReceiveQueue = [];
    private ?Suspension $datagramReceiveSuspension = null;

    private int $bidirectionalStreamId = -4;
    private int $unidirectionalStreamId = -2;

    /** @var array<int, \WeakReference<PairSocket>> */
    private array $openStreams = [];

    protected function __construct(bool $isServer, public QuicConfig $config)
    {
        $this->bindContext = (new BindContext)->withTlsContext(new ServerTlsContext);
        $ports = [1234, 9876];
        $this->localAddress = InternetAddress::fromString("127.0.0.1:{$ports[(int) $isServer]}");
        $this->remoteAddress = InternetAddress::fromString("127.0.0.1:{$ports[(int) !$isServer]}");

        if ($isServer) {
            ++$this->unidirectionalStreamId;
            ++$this->bidirectionalStreamId;
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function onClose(\Closure $onClose): void
    {
        if ($this->closed) {
            $onClose();
        } else {
            $this->onClosed[] = $onClose;
        }
    }

    public function maxDatagramSize(): int
    {
        return $this->maxDatagramSize;
    }

    public function close(QuicError|int $error = QuicError::NO_ERROR, string $reason = ""): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        foreach ($this->onClosed as $onClose) {
            $onClose();
        }
        foreach ($this->openStreams as $stream) {
            $stream->get()?->close();
        }
        $this->acceptSuspension?->resume();
        $this->datagramReceiveSuspension?->resume();
        $this->other->close();
    }

    public function accept(?Cancellation $cancellation = null): ?PairSocket
    {
        if ($this->closed) {
            return null;
        }

        if ($this->pendingAccepts) {
            $top = \current($this->pendingAccepts);
            unset($this->pendingAccepts[\key($this->pendingAccepts)]);
            return $top;
        }

        if ($this->acceptSuspension) {
            throw new PendingAcceptError;
        }

        $id = $cancellation?->subscribe(function ($e) {
            $this->acceptSuspension?->throw($e);
            $this->acceptSuspension = null;
        });
        $this->acceptSuspension = EventLoop::getSuspension();
        try {
            return $this->acceptSuspension->suspend();
        } finally {
            /** @psalm-suppress PossiblyNullArgument https://github.com/vimeo/psalm/issues/10553 */
            $cancellation?->unsubscribe($id);
        }
    }

    /** @internal */
    public function allocStreamId(PairSocket $socket): int
    {
        if ($socket->closed & PairSocket::UNREADABLE) {
            $id = $this->unidirectionalStreamId += 4;
        } else {
            $id = $this->bidirectionalStreamId += 4;
        }

        $this->openStreams[$id] = \WeakReference::create($socket);

        return $id;
    }

    /** @internal */
    public function sendSocket(PairSocket $socket): void
    {
        if ($this->other->acceptSuspension) {
            $this->other->acceptSuspension->resume($socket);
            $this->other->acceptSuspension = null;
        } else {
            $this->other->pendingAccepts[] = $socket;
        }
    }

    public function removeClosedStream(PairSocket $socket): void
    {
        unset($this->openStreams[$socket->getId()]);
    }

    public function openStream(): PairSocket
    {
        $a = new PairSocket($this);
        $b = new PairSocket($this->other);
        $a->other = $b;
        $b->other = $a;

        if (!$this->config->hasBidirectionalStreams()) {
            $a->closed = PairSocket::UNREADABLE;
            $b->closed = PairSocket::UNWRITABLE;
        }

        return $a;
    }

    public function getAddress(): InternetAddress
    {
        return $this->getRemoteAddress();
    }

    public function receive(?Cancellation $cancellation = null): ?string
    {
        if ($this->closed) {
            return null;
        }

        if ($this->datagramReceiveQueue) {
            $top = \current($this->datagramReceiveQueue);
            unset($this->datagramReceiveQueue[\key($this->datagramReceiveQueue)]);
            return $top;
        }

        if ($this->datagramReceiveSuspension) {
            throw new PendingReceiveError;
        }

        $id = $cancellation?->subscribe(function ($e) {
            $this->datagramReceiveSuspension->throw($e);
            $this->datagramReceiveSuspension = null;
        });

        $this->datagramReceiveSuspension = EventLoop::getSuspension();
        try {
            return $this->datagramReceiveSuspension->suspend();
        } finally {
            /** @psalm-suppress PossiblyNullArgument https://github.com/vimeo/psalm/issues/10553 */
            $cancellation?->unsubscribe($id);
        }
    }

    public function trySend(string $data): bool
    {
        $this->send($data);
        return true;
    }

    public function send(string $data, ?Cancellation $cancellation = null): void
    {
        if ($this->other->datagramReceiveSuspension) {
            $this->other->datagramReceiveSuspension->resume($data);
            $this->other->datagramReceiveSuspension = null;
        } else {
            $this->other->datagramReceiveQueue[] = $data;
        }
    }

    public function setupTls(?Cancellation $cancellation = null): void
    {
        // No-op, always setup
    }

    public function shutdownTls(?Cancellation $cancellation = null): void
    {
        if ($this->closed) {
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

    public function getTlsInfo(): TlsInfo
    {
        $tlsInfo = [];

        $cryptoInfo = [
            "alpn_protocol" => \current($this->config->getApplicationLayerProtocols()),
            "protocol" => "TLSv1.3",
            "cipher_name" => "<unknown>", // TODO not exposed in quiche FFI. Add upstream.
            "cipher_version" => "<unknown>",
            "cipher_bits" => 128, // unknown, but we need an int
        ];

        return TlsInfo::fromMetaData($cryptoInfo, $tlsInfo);
    }

    public function ping(): void
    {
        // noop?
    }

    public function getCloseReason(): QuicConnectionError
    {
        if (!$this->closed) {
            throw new \Error("getRemoteCloseReason() may not be called before the connection is closed.");
        }

        return $this->closeReason ?? new QuicConnectionError(QuicError::NO_ERROR, -1, "");
    }

    public function getStream(int $id): ?PairSocket
    {
        return ($this->openStreams[$id] ?? null)?->get();
    }

    /** @return array<never, never> */
    public function stats()
    {
        return [];
    }

    public function reference(): void
    {
        // No event loop here, nothing to do
    }

    public function unreference(): void
    {
        // No event loop here, nothing to do
    }

    public function getResource()
    {
        return null;
    }

    public function getBindContext(): BindContext
    {
        return $this->bindContext;
    }

    public function getLocalAddress(): InternetAddress
    {
        return $this->localAddress;
    }

    public function getRemoteAddress(): InternetAddress
    {
        return $this->remoteAddress;
    }
}
