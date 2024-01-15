<?php declare(strict_types=1);

namespace Amp\Quic\Internal\Quiche;

use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\Quic\Bindings\Quiche;
use Amp\Quic\Bindings\quiche_config_ptr;
use Amp\Quic\Bindings\quiche_recv_info_ptr;
use Amp\Quic\Bindings\struct_quiche_conn_ptr;
use Amp\Quic\Bindings\struct_sockaddr_ptr;
use Amp\Quic\QuicClientConfig;
use Amp\Quic\QuicConfig;
use Amp\Socket\ConnectException;
use Amp\Socket\InternetAddress;
use Amp\Socket\SocketAddress;
use Amp\TimeoutException;
use Revolt\EventLoop;

/**
 * @psalm-import-type QuicheClientConnection from QuicheConnection
 * @extends QuicheState<QuicClientConfig>
 */
final class QuicheClientState extends QuicheState
{
    /** @var \WeakReference<QuicheClientConnection>|null */
    private ?\WeakReference $connection = null; // avoid circular reference

    /**
     * @noinspection PhpPropertyOnlyWrittenInspection
     * @var QuicheClientConnection|null
     */
    private ?QuicheConnection $referenceConnectionInShutdown = null;

    private ?EventLoop\Suspension $startSuspension = null;

    private ?string $pingTimerId = null;

    /**
     * @param resource $socket
     *
     * @return QuicheClientConnection
     */
    public static function connect(
        string $host,
        $socket,
        QuicClientConfig $config,
        ?Cancellation $cancellation,
    ): QuicheConnection {
        $state = new self($config);

        $scid = \random_bytes(self::LOCAL_CONN_ID_LEN);
        /** @var InternetAddress $remoteAddress No unix sockets for you */
        $remoteAddress = SocketAddress\fromResourcePeer($socket);
        $remoteSockaddr = self::sockaddrFromInternetAddress($remoteAddress);
        /** @var InternetAddress $localAddress No unix sockets for you */
        $localAddress = SocketAddress\fromResourcePeer($socket);
        $localSockaddr = self::sockaddrFromInternetAddress($localAddress);

        $recv_info = quiche_recv_info_ptr::array();
        $recv_info->from = struct_sockaddr_ptr::castFrom($remoteSockaddr);
        $recv_info->from_len = Quiche::sizeof($remoteSockaddr[0]);
        $recv_info->to = struct_sockaddr_ptr::castFrom($localSockaddr);
        $recv_info->to_len = Quiche::sizeof($localSockaddr[0]);

        $state->startSuspension = $suspension = EventLoop::getSuspension();

        // defer on cancellation to avoid cancelling right during the startSuspension success, but execute at a time no microtasks are pending
        /** @psalm-suppress PossiblyNullReference */
        $cancellationId = $cancellation?->subscribe(
            fn ($e) => EventLoop::defer(static fn () => $suspension->throw($e))
        );

        /** @var struct_quiche_conn_ptr $conn */
        $readId = EventLoop::onReadable(
            $socket,
            static function (string $watcher, $socket) use ($state, $recv_info, &$conn): void {
                if (false === $buf = \stream_socket_recvfrom($socket, 65507)) {
                    // handle critical local stream error
                    /** @psalm-suppress TypeDoesNotContainNull, RedundantCondition */
                    $state->startSuspension?->throw(
                        new ConnectException("Could not establish connection: stream_socket_recvfrom failed")
                    );
                    $state->startSuspension = null;
                    EventLoop::cancel($state->readIds[0]);
                    return;
                }

                do {
                    $success = self::$quiche->quiche_conn_recv($conn, $buf, \strlen($buf), $recv_info);
                    if ($success < 0) {
                        // error, log it?
                        \user_error("Failed quiche_conn_recv: $success");
                        return;
                    }
                } while (false !== $buf = \stream_socket_recvfrom($socket, self::MAX_DATAGRAM_SIZE));

                $quicConnection = $state->connection?->get();
                \assert($quicConnection !== null);
                if ($state->checkReceive($quicConnection)) {
                    /** @psalm-suppress TypeDoesNotContainNull, RedundantCondition */
                    $state->startSuspension?->resume();
                    $state->startSuspension = null;

                    if ($state->pingTimerId) {
                        // restart timer
                        EventLoop::disable($state->pingTimerId);
                        EventLoop::enable($state->pingTimerId);
                    }
                }

                $state->checkSend($quicConnection);
            }
        );

        $state->readIds = [$readId];

        $state->installWriteHandler($socket);

        $conn = QuicheState::$quiche->quiche_connect(
            $host,
            $scid,
            \strlen($scid),
            $recv_info->to,
            $recv_info->to_len,
            $recv_info->from,
            $recv_info->from_len,
            $state->quicheConfig,
        );

        \assert($conn !== null);

        //self::$quiche->quiche_conn_set_qlog_path($conn, __DIR__ . "/../qlog.log", "log", "log");
        $quicConnection = new QuicheConnection(
            $state,
            $socket,
            $localAddress,
            $localSockaddr,
            $remoteAddress,
            $remoteSockaddr,
            $conn,
        );

        $state->connection = $connectionRef = \WeakReference::create($quicConnection);

        if (-1 > $err = $state->trySendConnection($quicConnection)) {
            throw new ConnectException("Count not establish connection: $err");
        }

        $timeout = EventLoop::delay($config->getHandshakeTimeout(), function () use ($state) {
            $state->startSuspension->throw(
                new ConnectException("Connection timed out", previous: new TimeoutException())
            );
            $state->startSuspension = null;
        });

        try {
            $state->startSuspension->suspend();
        } finally {
            EventLoop::cancel($timeout);
            EventLoop::unreference($state->readIds[0]);

            /** @psalm-suppress PossiblyNullArgument https://github.com/vimeo/psalm/issues/10553 */
            $cancellation?->unsubscribe($cancellationId);
        }

        if ($pingPeriod = $config->getPingPeriod()) {
            $state->pingTimerId = EventLoop::repeat($pingPeriod, static function () use ($connectionRef): void {
                $quicConnection = $connectionRef->get();
                \assert($quicConnection !== null);
                $quicConnection->ping();
            });
            EventLoop::unreference($state->pingTimerId);
        }

        return $quicConnection;
    }

    private function cancelPing(): void
    {
        if ($this->pingTimerId) {
            EventLoop::cancel($this->pingTimerId);
            $this->pingTimerId = null;
        }
    }

    public function closeConnection(
        QuicheConnection $quicConnection,
        bool $applicationError,
        int $error,
        string $reason,
    ): void {
        parent::closeConnection($quicConnection, $applicationError, $error, $reason);
        $this->referenceConnectionInShutdown = $quicConnection;
        $this->cancelPing();
    }

    /** @param QuicheClientConnection $connection */
    public function signalConnectionClosed(QuicheConnection $connection): void
    {
        if ($this->startSuspension) {
            $this->startSuspension->throw(new StreamException("Connection attempt rejected"));
            $this->startSuspension = null;
        }
        parent::signalConnectionClosed($connection);
        $this->free();
    }

    protected function free(): void
    {
        parent::free();
        $this->cancelPing();
        $this->referenceConnectionInShutdown = null;
    }

    /**
     * @param QuicClientConfig $config
     */
    protected function applyConfig(QuicConfig $config): quiche_config_ptr
    {
        $cfg = parent::applyConfig($config);

        if ($certificate = $config->getCertificate()) {
            self::$quiche->quiche_config_load_cert_chain_from_pem_file($cfg, $certificate->getCertFile());
            self::$quiche->quiche_config_load_priv_key_from_pem_file($cfg, $certificate->getKeyFile());
        }

        return $cfg;
    }

    // No explicit __destruct() here, it's managed through the destructor of QuicheConnection, which will close the
    // connection: signalConnectionClosed() will then eventually free it.
}
