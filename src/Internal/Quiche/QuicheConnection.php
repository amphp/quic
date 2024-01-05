<?php

namespace Amp\Quic\Internal\Quiche;

use Amp\ByteStream\ClosedException;
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
use Closure;
use Kelunik\Certificate\Certificate;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use WeakReference;

// Note: A QuicheConnection holds the socket it is currently "connected" to. It may be migrated any time to another socket, e.g. when a peer sends from another socket.
// For example, when listening on :: and 0.0.0.0, a peer may transition at any time between the IPv4 and IPv6. At that point the socket on the connection will also change.
final class QuicheConnection implements \Amp\Quic\QuicConnection
{
    private ?Suspension $acceptor = null;
    private bool $referenced = true;
    private bool $closed = false;

    /** @var WeakReference<QuicheSocket>[] */
    private array $streams = [];
    public ?DeferredFuture $onClose = null;

    public ?Suspension $datagramSuspension = null;

    /** @var int[] */
    public array $streamQueue = [];

    public float $nextTimer = 0;
    public string $timer;

    public int $bidirectionalStreamId = -4;
    public int $unidirectionalStreamId = -2;
    private ?TlsInfo $tlsInfo = null;

    private \Closure $cancel;
    public ?string $establishingTimer;

    public bool $queuedSend = false;
    /** @var array{string, Suspension|null}[] */
    public array $datagramWrites = [];
    private QuicConnectionError $error;

    public int $lastReceiveTime;
    public int $pingInsertionTime = -1;

    /** @param $socket resource */
    public function __construct(
        public QuicheState $state,
        public $socket,
        public InternetAddress $localAddress,
        public struct_sockaddr_in_ptr | struct_sockaddr_in6_ptr $localSockaddr,
        public InternetAddress $address,
        public struct_sockaddr_in_ptr | struct_sockaddr_in6_ptr $sockaddr,
        public quiche_conn_ptr $connection,
        public ?string $dcid_string = null
    ) {
        $acceptor = &$this->acceptor;
        $this->cancel = static function (CancelledException $exception) use (&$acceptor): void {
            $acceptor?->throw($exception);
            $acceptor = null;
        };
        if ($dcid_string !== null) { // if is server
            ++$this->unidirectionalStreamId;
            ++$this->bidirectionalStreamId;
        }
        if (!$state->config->hasBidirectionalStreams()) {
            unset($this->bidirectionalStreamId);
        }
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
            $errorCode = $applicationError ? $error : $error->value;
            $this->state->closeConnection($this, $applicationError, $errorCode, $reason);
            $this->error = new QuicConnectionError($applicationError ? null : $error, $errorCode, $reason);
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
            $socket = $this->streams[$stream] = new QuicheSocket($this, $stream);
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
        return (new BindContext)->withTlsContext($config->getTlsContext());
    }

    public function notifyReadable(int $stream)
    {
        EventLoop::defer(function () use ($stream) {
            if (!isset($this->streams[$stream])) {
                if (isset($this->acceptor)) {
                    $socket = new QuicheSocket($this, $stream);
                    $this->streams[$stream] = WeakReference::create($socket);
                    $socket->readPending = true;
                    $this->acceptor->resume($socket);
                } else {
                    $this->streamQueue[$stream] = null;
                }
            } else {
                /** @noinspection NullPointerExceptionInspection */
                $this->streams[$stream]->get()->notifyReadable();
            }
        });
    }

    public function notifyWritable(int $stream)
    {
        if (isset($this->streams[$stream])) {
            /** @noinspection NullPointerExceptionInspection */
            $this->streams[$stream]->get()->notifyWritable();
        }
    }

    public function notifyClosed()
    {
        $this->cancelTimer();
        if (!$this->closed) {
            $this->clean();
            if (QuicheState::$quiche->quiche_conn_peer_error($this->connection, [&$is_app], [&$error_code], [&$reason], [&$reason_len])) {
                $this->error = new QuicConnectionError($is_app ? null : QuicError::tryFrom($error_code), $error_code, $reason->toString($reason_len));
            }
        }
        QuicheState::$quiche->quiche_conn_free($this->connection);
        unset($this->connection, $this->socket);
    }

    private function clean()
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

    public function cancelTimer()
    {
        if ($this->nextTimer) {
            $this->nextTimer = 0;
            EventLoop::cancel($this->timer);
        }
    }

    public function shutdownStream(QuicheSocket $socket, bool $writing, int $reason = 0)
    {
        $close = $socket->closed | ($writing ? QuicheSocket::UNWRITABLE : QuicheSocket::UNREADABLE);
        if ($close !== $socket->closed) {
            if (!$this->closed && isset($socket->id)) {
                QuicheState::$quiche->quiche_conn_stream_shutdown($this->connection, $socket->id, $writing, $reason);
                $this->state->checkSend($this);
            }
            $socket->closed = $close;

            if (!$writing) {
                $socket->reader?->resume();
                if ($socket->onClose?->isComplete() === false) {
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

    public function closeStream(QuicheSocket $socket, int $reason = 0, bool $hardClose = false)
    {
        $this->shutdownStream($socket, false, $reason);
        if ($hardClose) {
            $this->shutdownStream($socket, true, $reason);
        } else {
            $socket->end();
        }

        if (isset($socket->id)) {
            unset($this->streams[$socket->id]);
        }
    }

    public function openStream(): QuicheSocket
    {
        $socket = new QuicheSocket($this);
        if (!isset($this->bidirectionalStreamId)) {
            $socket->closed = QuicheSocket::UNREADABLE;
        }
        return $socket;
    }

    // To avoid having gaps in stream ids which we'll have to explicitly close, we opt to defer allocating stream ids until data is actually sent on a stream
    public function allocStreamId(QuicheSocket $socket)
    {
        if ($socket->closed & QuicheSocket::UNREADABLE) {
            $id = $this->unidirectionalStreamId += 4;
        } else {
            $id = $this->bidirectionalStreamId += 4;
        }
        $this->streams[$id] = WeakReference::create($socket);
        $socket->id = $id;

        if (isset($socket->priority)) {
            $socket->setPriority($socket->priority, $socket->incremental);
        }
    }

    public function receive(?Cancellation $cancellation = null, ?int $limit = null): ?array
    {
        $datagram = $this->receiveDatagram($cancellation, $limit);
        return $datagram === null ? null : [$datagram, $this->address];
    }

    public function send(?InternetAddress $address, string $data): void
    {
        // We'll ignore $address, it's meaningless on a QUIC connection
        $this->sendDatagram($data);
    }

    public function receiveDatagram(?Cancellation $cancellation = null, ?int $limit = null): ?string
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
            $this->datagramSuspension->throw($exception);
        });

        try {
            if ($this->referenced) {
                $this->state->reference();
            }
            $data = $this->datagramSuspension->suspend();
            if ($limit === null || $data === null) {
                return $data;
            }
            return \substr($data, 0, $limit);
        } finally {
            if ($this->referenced) {
                $this->state->unreference();
            }
            $cancellation?->unsubscribe($id);
        }
    }

    public function sendDatagram(string $data, ?Cancellation $cancellation = null): void
    {
        if ($this->datagramWrites || !$this->trySendDatagram($data)) {
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

    public function trySendDatagram(string $data): bool
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
        QuicheState::$quiche->quiche_conn_peer_cert($this->connection, [&$buf], [&$len]);
        if ($len > 0) {
            $tlsInfo["peer_certificate"] = Certificate::derToPem($buf->toString($len));
        }
        // TODO peer_ca_certs, not exposed in quiche FFI. Add upstream.

        QuicheState::$quiche->quiche_conn_application_proto($this->connection, [&$buf], [&$len]);
        $cryptoInfo["alpn_protocol"] = $buf->toString($len);
        $cryptoInfo["protocol"] = "TLSv1.3";
        // TODO not exposed in quiche FFI. Add upstream.
        $cryptoInfo["cipher_name"] = "<unknown>";
        $cryptoInfo["cipher_version"] = "<unknown>";
        $cryptoInfo["cipher_bits"] = 128; // unknown, but we need an int

        return $this->tlsInfo = TlsInfo::fromMetaData($cryptoInfo, $tlsInfo);
    }

    public function __destruct()
    {
        $this->close();
    }

    public function handshakeTimeout()
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

    public function stats()
    {
        if (!isset($this->connection)) {
            throw new ClosedException("The connection was freed.");
        }

        /** @var quiche_stats $ffiStats */
        QuicheState::$quiche->quiche_conn_stats($this->connection, [&$ffiStats]);

        $stats = new QuicheStats;
        $stats->recv = $ffiStats->recv;
        $stats->sent = $ffiStats->sent;
        $stats->lost = $ffiStats->lost;
        $stats->retrans = $ffiStats->retrans;
        $stats->sentBytes = $ffiStats->sent_bytes;
        $stats->recvBytes = $ffiStats->recv_bytes;
        $stats->lostBytes = $ffiStats->lost_bytes;
        $stats->streamRetransBytes = $ffiStats->stream_retrans_bytes;
        $stats->resetStreamCountLocal = $ffiStats->reset_stream_count_local;
        $stats->stoppedStreamCountLocal = $ffiStats->stopped_stream_count_local;
        $stats->resetStreamCountRemote = $ffiStats->reset_stream_count_remote;
        $stats->stoppedStreamCountRemote = $ffiStats->stopped_stream_count_remote;

        for ($i = 0, $paths = $ffiStats->paths_count; $i < $paths; ++$i) {
            /** @var quiche_path_stats $ffiPath */
            QuicheState::$quiche->quiche_conn_path_stats($this->connection, $i, [&$ffiPath]);

            $stats->paths[] = $path = new QuichePathStats;

            $path->localAddr = QuicheState::fromSockaddr($ffiPath->local_addr);
            $path->peerAddr = QuicheState::fromSockaddr($ffiPath->peer_addr);
            $path->validationState = $ffiPath->validation_state;
            $path->active = $ffiPath->active;
            $path->recv = $ffiPath->recv;
            $path->sent = $ffiPath->sent;
            $path->lost = $ffiPath->lost;
            $path->retrans = $ffiPath->retrans;
            $path->rtt = $ffiPath->rtt;
            $path->cwnd = $ffiPath->cwnd;
            $path->sentBytes = $ffiPath->sent_bytes;
            $path->recvBytes = $ffiPath->recv_bytes;
            $path->lostBytes = $ffiPath->lost_bytes;
            $path->streamRetransBytes = $ffiPath->stream_retrans_bytes;
            $path->pmtu = $ffiPath->pmtu;
            $path->deliveryRate = $ffiPath->delivery_rate;
        }

        return $stats;
    }
}
