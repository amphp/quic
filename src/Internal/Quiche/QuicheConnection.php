<?php

namespace Amp\Quic\Internal\Quiche;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\Quic\Bindings\Quiche;
use Amp\Quic\Bindings\quiche_conn_ptr;
use Amp\Quic\Bindings\quiche_path_stats;
use Amp\Quic\Bindings\quiche_stats;
use Amp\Quic\Bindings\struct_sockaddr_in6_ptr;
use Amp\Quic\Bindings\struct_sockaddr_in_ptr;
use Amp\Quic\QuicConnectionError;
use Amp\Quic\QuicError;
use Amp\Quic\Quiche\QuichePathStats;
use Amp\Quic\Quiche\QuicheStats;
use Amp\Quic\QuicServerConfig;
use Amp\Quic\QuicSocket;
use Amp\Socket\BindContext;
use Amp\Socket\InternetAddress;
use Amp\Socket\PendingAcceptError;
use Amp\Socket\PendingReceiveError;
use Amp\Socket\SocketException;
use Amp\Socket\TlsInfo;
use Amp\Socket\TlsState;
use Closure;
use Kelunik\Certificate\Certificate;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

// Note: A QuicheConnection holds the socket it is currently "connected" to. It may be migrated any time to another socket, e.g. when a peer sends from another socket.
// For example, when listening on :: and 0.0.0.0, a peer may transition at any time between the IPv4 and IPv6. At that point the socket on the connection will also change.
/**
 * @template IsClient of bool
 * @psalm-type QuicheClientConnection = QuicheConnection<true>
 * @psalm-type QuicheServerConnection = QuicheConnection<false>
 */
final class QuicheConnection implements \Amp\Quic\QuicConnection
{
    private ?Suspension $acceptor = null;
    private bool $referenced = true;
    private bool $closed = false;

    /** @psalm-var array<int, \WeakReference<QuicheSocket>> */
    private array $streams = [];
    public ?DeferredFuture $onClose = null;

    public ?Suspension $datagramSuspension = null;

    /** @var array<int, null> */
    public array $streamQueue = [];

    public float $nextTimer = 0;
    public string $timer;

    public int $bidirectionalStreamId = -4;
    public int $unidirectionalStreamId = -2;
    private ?TlsInfo $tlsInfo = null;

    private \Closure $cancel;
    public ?string $establishingTimer;

    public bool $queuedSend = false;
    /** @psalm-var array<list{string, Suspension}> */
    public array $datagramWrites = [];
    private QuicConnectionError $error;

    public int $lastReceiveTime;
    public int $pingInsertionTime = -1;

    public function __construct(
        public QuicheState $state,
        /** @var resource */
        public $socket,
        public InternetAddress $localAddress,
        public struct_sockaddr_in_ptr | struct_sockaddr_in6_ptr $localSockaddr,
        public InternetAddress $address,
        public struct_sockaddr_in_ptr | struct_sockaddr_in6_ptr $sockaddr,
        public quiche_conn_ptr $connection,
        // TODO: psalm: Umm, why can't it ever be null? According to type it's null or string, right?
        /** @psalm-var (IsClient is true ? null : string) */
        public ?string $dcid_string = null
    ) {
        $acceptor = &$this->acceptor;
        $this->cancel = static function (CancelledException $exception) use (&$acceptor): void {
            $acceptor?->throw($exception);
            $acceptor = null;
        };
        // TODO: psalm: Umm, why can't it ever be null? According to type it's null or string, right?
        if ($dcid_string !== null) { // if is server
            ++$this->unidirectionalStreamId;
            ++$this->bidirectionalStreamId;
        }
        if (!$state->config->hasBidirectionalStreams()) {
            unset($this->bidirectionalStreamId);
        }
        /** @psalm-suppress RedundantCondition */
        if (isset($state->keylogPattern)) {
            $path = \str_replace("%h", $address->toString(), $state->keylogPattern);
            if (!QuicheState::$quiche->quiche_conn_set_keylog_path($connection, $path)) {
                EventLoop::queue(fn () => throw new \Exception("Could not successfully enable keylog on the QUIC connection."));
            }
        }
    }

    public function close(int | QuicError $error = QuicError::NO_ERROR, string $reason = ""): void
    {
        if (!$this->closed) {
            $applicationError = \is_int($error);
            // https://github.com/vimeo/psalm/issues/10555
            $errorCode = \is_int($error) ? $error : $error->value;
            $this->state->closeConnection($this, $applicationError, $errorCode, $reason);
            $this->error = new QuicConnectionError(\is_int($error) ? null : $error, $errorCode, $reason);
            $this->clean();
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function onClose(Closure $onClose): void
    {
        ($this->onClose ??= new DeferredFuture)->getFuture()->finally($onClose);
    }

    public function reference(): void
    {
        if (!$this->referenced) {
            $this->referenced = true;
            if ($this->acceptor || $this->datagramSuspension) {
                $this->state->reference();
            }
        }
    }

    public function unreference(): void
    {
        if ($this->referenced) {
            $this->referenced = false;
            if ($this->acceptor || $this->datagramSuspension) {
                $this->state->unreference();
            }
        }
    }

    /** @return resource */
    public function getResource()
    {
        return $this->socket;
    }

    public function accept(?Cancellation $cancellation = null): ?QuicSocket
    {
        if ($this->closed) {
            throw new ClosedException;
        }

        if (isset($this->acceptor)) {
            throw new PendingAcceptError;
        }

        if ($this->streamQueue) {
            $stream = \key($this->streamQueue);
            unset($this->streamQueue[$stream]);
            $socket = new QuicheSocket($this, $stream);
            $this->streams[$stream] = \WeakReference::create($socket);
            $socket->readPending = true;
            return $socket;
        }

        $id = $cancellation?->subscribe($this->cancel);

        $this->acceptor = EventLoop::getSuspension();
        if ($this->referenced) {
            $this->state->reference();
        }

        try {
            return $this->acceptor->suspend();
        } finally {
            $this->acceptor = null;
            if ($this->referenced) {
                $this->state->unreference();
            }

            /** @psalm-suppress PossiblyNullArgument $id is always defined if $cancellation is non-null */
            $cancellation?->unsubscribe($id);
        }
    }

    public function getAddress(): InternetAddress
    {
        return $this->localAddress;
    }

    public function getLocalAddress(): InternetAddress
    {
        return $this->localAddress;
    }

    public function getRemoteAddress(): InternetAddress
    {
        return $this->address;
    }

    public function getBindContext(): BindContext
    {
        $config = $this->state->config;
        if ($config instanceof QuicServerConfig) {
            return $config->getBindContext();
        }
        // We have to return something if it's a client, because of the interface.
        return new BindContext;
    }

    public function notifyReadable(int $stream): void
    {
        EventLoop::defer(function () use ($stream) {
            if (!isset($this->streams[$stream])) {
                if (isset($this->acceptor)) {
                    $socket = new QuicheSocket($this, $stream);
                    $this->streams[$stream] = \WeakReference::create($socket);
                    $socket->readPending = true;
                    $this->acceptor->resume($socket);
                } else {
                    $this->streamQueue[$stream] = null;
                }
            } else {
                /**
                 * @noinspection NullPointerExceptionInspection
                 * @psalm-suppress PossiblyNullReference
                 */
                $this->streams[$stream]->get()->notifyReadable();
            }
        });
    }

    public function notifyWritable(int $stream): void
    {
        if (isset($this->streams[$stream])) {
            /**
             * @noinspection NullPointerExceptionInspection
             * @psalm-suppress PossiblyNullReference
             */
            $this->streams[$stream]->get()->notifyWritable();
        }
    }

    public function notifyClosed(): void
    {
        $this->cancelTimer();
        if (!$this->closed) {
            $this->clean();
            // https://github.com/vimeo/psalm/issues/10551
            /** @psalm-suppress UndefinedVariable */
            if (QuicheState::$quiche->quiche_conn_peer_error($this->connection, [&$is_app], [&$error_code], [&$reason], [&$reason_len])) {
                $this->error = new QuicConnectionError($is_app ? null : QuicError::tryFrom($error_code), $error_code, $reason->toString($reason_len));
            }
        }
        QuicheState::$quiche->quiche_conn_free($this->connection);
        unset($this->connection, $this->socket);
    }

    private function clean(): void
    {
        $this->closed = true;
        $this->acceptor?->resume();
        $this->datagramSuspension?->resume();
        foreach ($this->streams as $stream) {
            $stream->get()?->close();
        }
        if ($this->datagramWrites) {
            $exception = new ClosedException("The socket was closed before writing completed");
            foreach ($this->datagramWrites as [, $suspension]) {
                $suspension->throw($exception);
            }
            $this->datagramWrites = [];
        }
        $this->onClose?->complete();
    }

    public function cancelTimer(): void
    {
        if ($this->nextTimer) {
            $this->nextTimer = 0;
            EventLoop::cancel($this->timer);
        }
    }

    public function shutdownStream(QuicheSocket $socket, bool $writing, int $reason = 0): void
    {
        $close = $socket->closed | ($writing ? QuicheSocket::UNWRITABLE : QuicheSocket::UNREADABLE);
        if ($close !== $socket->closed) {
            /** @psalm-suppress RedundantCondition */
            if (!$this->closed && isset($socket->id)) {
                QuicheState::$quiche->quiche_conn_stream_shutdown($this->connection, $socket->id, (int) $writing, $reason);
                $this->state->checkSend($this);
            }
            $socket->closed = $close;

            if (!$writing) {
                $socket->reader?->resume();
                $socket->reader = null;
                if ($socket->onClose?->isComplete() === false) {
                    // https://github.com/vimeo/psalm/issues/10554
                    /** @psalm-suppress NullReference */
                    $socket->onClose->complete();
                }
            } elseif (!$socket->writes->isEmpty()) {
                $exception = new ClosedException("The socket was closed before writing completed");
                do {
                    /** @var Suspension|null $suspension */
                    [, $suspension] = $socket->writes->shift();
                    $suspension?->throw($exception);
                } while (!$socket->writes->isEmpty());
            }

        }
    }

    public function closeStream(QuicheSocket $socket, int $reason = 0, bool $hardClose = false): void
    {
        $this->shutdownStream($socket, false, $reason);
        if ($hardClose) {
            $this->shutdownStream($socket, true, $reason);
        } else {
            $socket->end();
        }

        /** @psalm-suppress all */
        if (isset($socket->id)) {
            unset($this->streams[$socket->id]);
        }
    }

    public function openStream(): QuicheSocket
    {
        $socket = new QuicheSocket($this);
        /** @psalm-suppress TypeDoesNotContainType */
        if (!isset($this->bidirectionalStreamId)) {
            $socket->closed = QuicheSocket::UNREADABLE;
        }
        return $socket;
    }

    /** To avoid having gaps in stream ids which we'll have to explicitly close, we opt to defer allocating stream ids until data is actually sent on a stream */
    public function allocStreamId(QuicheSocket $socket): void
    {
        if ($socket->closed & QuicheSocket::UNREADABLE) {
            $id = $this->unidirectionalStreamId += 4;
        } else {
            $id = $this->bidirectionalStreamId += 4;
        }
        $this->streams[$id] = \WeakReference::create($socket);
        $socket->id = $id;

        if ($socket->priority !== 127 || !$socket->incremental) {
            $socket->setPriority($socket->priority, $socket->incremental);
        }
    }

    public function receive(?Cancellation $cancellation = null): ?string
    {
        if ($this->closed) {
            return null;
        }

        if ($this->datagramSuspension) {
            throw new PendingReceiveError;
        }

        if (0 < $size = QuicheState::$quiche->quiche_conn_dgram_recv($this->connection, QuicheState::$sendBuffer, QuicheState::SEND_BUFFER_SIZE)) {
            return QuicheState::$sendBuffer->toString($size);
        }

        $this->datagramSuspension = EventLoop::getSuspension();

        $id = $cancellation?->subscribe(function ($exception) {
            \assert($this->datagramSuspension !== null);
            $this->datagramSuspension->throw($exception);
        });

        try {
            if ($this->referenced) {
                $this->state->reference();
            }
            return $this->datagramSuspension->suspend();
        } finally {
            if ($this->referenced) {
                $this->state->unreference();
            }
            // https://github.com/vimeo/psalm/issues/10553
            /** @psalm-suppress PossiblyNullArgument */
            $cancellation?->unsubscribe($id);
        }
    }

    public function send(string $data, ?Cancellation $cancellation = null): void
    {
        if ($this->datagramWrites || !$this->trySend($data)) {
            $suspension = EventLoop::getSuspension();
            $this->datagramWrites[] = [$data, $suspension];

            if ($cancellation) {
                $key = \array_key_last($this->datagramWrites);
                $id = $cancellation->subscribe(function ($exception) use ($key) {
                    if (isset($this->datagramWrites[$key])) {
                        [, $suspension] = $this->datagramWrites[$key];
                        $suspension->throw($exception);
                        unset($this->datagramWrites[$key]);
                    }
                });
                try {
                    $suspension->suspend();
                } finally {
                    $cancellation->unsubscribe($id);
                }
            } else {
                $suspension->suspend();
            }
        }
    }

    public function trySend(string $data): bool
    {
        if ($this->closed) {
            throw new ClosedException;
        }

        $ret = QuicheState::$quiche->quiche_conn_dgram_send($this->connection, $data, \strlen($data));
        if ($ret < 0) {
            if ($ret === Quiche::QUICHE_ERR_DONE) {
                return false;
            }

            if ($ret === Quiche::QUICHE_ERR_INVALID_STATE) {
                throw new SocketException("Could not send datagram on connection where no datagrams are accepted by the peer");
            }

            if ($ret === Quiche::QUICHE_ERR_BUFFER_TOO_SHORT) {
                throw new SocketException("Could not send a datagram with a size larger than maxDatagramSize().");
            }

            throw new SocketException("Unexpected datagram error: $ret");
        }

        $this->state->checkSend($this);
        return true;
    }

    public function maxDatagramSize(): int
    {
        return QuicheState::$quiche->quiche_conn_dgram_max_writable_len($this->connection);
    }

    public function getTlsInfo(): TlsInfo
    {
        if ($this->tlsInfo !== null) {
            return $this->tlsInfo;
        }

        $tlsInfo = [];
        // https://github.com/vimeo/psalm/issues/10551
        /** @psalm-suppress UndefinedVariable */
        QuicheState::$quiche->quiche_conn_peer_cert($this->connection, [&$buf], [&$len]);
        if ($len > 0) {
            $tlsInfo["peer_certificate"] = Certificate::derToPem($buf->toString($len));
        }
        // TODO peer_ca_certs, not exposed in quiche FFI. Add upstream.

        // https://github.com/vimeo/psalm/issues/10551
        /** @psalm-suppress UndefinedVariable */
        QuicheState::$quiche->quiche_conn_application_proto($this->connection, [&$buf], [&$len]);
        $cryptoInfo = [
            "alpn_protocol" => $buf->toString($len),
            "protocol" => "TLSv1.3",
            "cipher_name" => "<unknown>", // TODO not exposed in quiche FFI. Add upstream.
            "cipher_version" => "<unknown>",
            "cipher_bits" => 128, // unknown, but we need an int
        ];

        return $this->tlsInfo = TlsInfo::fromMetaData($cryptoInfo, $tlsInfo);
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

    public function __destruct()
    {
        $this->close();
    }

    public function handshakeTimeout(): void
    {
        $this->close(QuicError::CONNECTION_REFUSED, "Handshake timeout");
    }

    public function ping(): void
    {
        if ($this->closed) {
            throw new ClosedException;
        }

        QuicheState::$quiche->quiche_conn_send_ack_eliciting($this->connection);
        $this->state->checkSend($this);
    }

    public function getCloseReason(): QuicConnectionError
    {
        if (!$this->closed) {
            throw new \Error("getRemoteCloseReason() may not be called before the connection is closed.");
        }

        return $this->error ?? new QuicConnectionError(QuicError::NO_ERROR, -1, "");
    }

    public function getStream(int $id): ?QuicSocket
    {
        return isset($this->streams[$id]) ? $this->streams[$id]->get() : null;
    }

    public function stats(): QuicheStats
    {
        /** @psalm-suppress TypeDoesNotContainType */
        if (!isset($this->connection)) {
            throw new ClosedException("The connection was freed.");
        }

        /** @var quiche_stats $ffiStats */
        QuicheState::$quiche->quiche_conn_stats($this->connection, [&$ffiStats]);

        $paths = [];
        for ($i = 0, $pathCount = $ffiStats->paths_count; $i < $pathCount; ++$i) {
            /** @var quiche_path_stats $ffiPath */
            QuicheState::$quiche->quiche_conn_path_stats($this->connection, $i, [&$ffiPath]);

            $paths[] = new QuichePathStats(
                localAddr: QuicheState::fromSockaddr($ffiPath->local_addr),
                peerAddr: QuicheState::fromSockaddr($ffiPath->peer_addr),
                validationState: $ffiPath->validation_state,
                active: $ffiPath->active,
                recv: $ffiPath->recv,
                sent: $ffiPath->sent,
                lost: $ffiPath->lost,
                retrans: $ffiPath->retrans,
                rtt: $ffiPath->rtt,
                cwnd: $ffiPath->cwnd,
                sentBytes: $ffiPath->sent_bytes,
                recvBytes: $ffiPath->recv_bytes,
                lostBytes: $ffiPath->lost_bytes,
                streamRetransBytes: $ffiPath->stream_retrans_bytes,
                pmtu: $ffiPath->pmtu,
                deliveryRate: $ffiPath->delivery_rate,
            );
        }

        return new QuicheStats(
            recv: $ffiStats->recv,
            sent: $ffiStats->sent,
            lost: $ffiStats->lost,
            retrans: $ffiStats->retrans,
            sentBytes: $ffiStats->sent_bytes,
            recvBytes: $ffiStats->recv_bytes,
            lostBytes: $ffiStats->lost_bytes,
            streamRetransBytes: $ffiStats->stream_retrans_bytes,
            paths: $paths,
            resetStreamCountLocal: $ffiStats->reset_stream_count_local,
            stoppedStreamCountLocal: $ffiStats->stopped_stream_count_local,
            resetStreamCountRemote: $ffiStats->reset_stream_count_remote,
            stoppedStreamCountRemote: $ffiStats->stopped_stream_count_remote,
        );
    }
}
