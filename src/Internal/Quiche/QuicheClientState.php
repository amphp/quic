<?php

namespace Amp\Quic\Internal\Quiche;

use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\Quic\Bindings\Quiche;
use Amp\Quic\Bindings\quiche_config_ptr;
use Amp\Quic\Bindings\quiche_recv_info_ptr;
use Amp\Quic\Bindings\struct_sockaddr_ptr;
use Amp\Quic\QuicClientConfig;
use Amp\Quic\QuicConfig;
use Amp\Quic\QuicConnection;
use Amp\Socket\ConnectException;
use Amp\Socket\InternetAddress;
use Amp\Socket\SocketAddress;
use Amp\TimeoutException;
use Revolt\EventLoop;

/** @property QuicClientConfig $config */
class QuicheClientState extends QuicheState
{
    /** @var \WeakReference<QuicheConnection> */
    private \WeakReference $connection; // avoid circular reference
    /** @noinspection PhpPropertyOnlyWrittenInspection */
    private QuicheConnection $referenceConnectionInShutdown;

    private ?EventLoop\Suspension $startSuspension;
    private string $pingTimerId;

    /** @param $socket resource */
    public static function connect(string $host, $socket, QuicClientConfig $config, ?Cancellation $cancellation): QuicheConnection
    {
        $state = new self($config);

        $scid = \random_bytes(self::LOCAL_CONN_ID_LEN);
        /** @var InternetAddress $remoteAddress No unix sockets for you */
        $remoteAddress = SocketAddress\fromResourcePeer($socket);
        $remoteSockaddr = self::sockaddrFromInternetAddress($remoteAddress);
        /** @var InternetAddress $localAddress No unix sockets for you */
        $localAddress = SocketAddress\fromResourcePeer($socket);
        $localSockaddr = self::sockaddrFromInternetAddress($localAddress);

        $state->startSuspension = EventLoop::getSuspension();

        // defer on cancellation to avoid cancelling right during the startSuspension success, but execute at a time no microtasks are pending
        $cancellationId = $cancellation?->subscribe(fn ($e) => EventLoop::defer(fn () => $state->startSuspension->throw($e)));

        $state->readIds = [EventLoop::onReadable($socket, function (string $watcher, $socket) use ($state, &$recv_info, &$conn): void {
            if (false === $buf = \stream_socket_recvfrom($socket, 65507)) {
                // handle critical local stream error
                if ($state->startSuspension) {
                    $state->startSuspension->throw(new ConnectException("Could not establish connection: stream_socket_recvfrom failed"));
                    $state->startSuspension = null;
                }
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

            /** @var QuicConnection $quicConnection it's never null */
            $quicConnection = $state->connection->get();
            if ($state->checkReceive($quicConnection)) {
                if ($state->startSuspension) {
                    $state->startSuspension->resume();
                    $state->startSuspension = null;
                }
                if (isset($state->pingTimerId)) {
                    // restart timer
                    EventLoop::disable($state->pingTimerId);
                    EventLoop::enable($state->pingTimerId);
                }
            }

            $state->checkSend($quicConnection);
        })];

        $state->installWriteHandler($socket);

        $recv_info = quiche_recv_info_ptr::array();
        $recv_info->from = struct_sockaddr_ptr::castFrom($remoteSockaddr);
        $recv_info->from_len = Quiche::sizeof($remoteSockaddr[0]);
        $recv_info->to = struct_sockaddr_ptr::castFrom($localSockaddr);
        $recv_info->to_len = Quiche::sizeof($localSockaddr[0]);

        $conn = QuicheState::$quiche->quiche_connect($host, $scid, \strlen($scid), $recv_info->to, $recv_info->to_len, $recv_info->from, $recv_info->from_len, $state->quicheConfig);
        //self::$quiche->quiche_conn_set_qlog_path($conn, __DIR__ . "/../qlog.log", "log", "log");
        $quicConnection = new QuicheConnection($state, $socket, $localAddress, $localSockaddr, $remoteAddress, $remoteSockaddr, $conn);
        $state->connection = \WeakReference::create($quicConnection);

        $timeout = EventLoop::delay($config->getHandshakeTimeout(), fn () => $state->startSuspension->throw(new ConnectException("Connection timed out", previous: new TimeoutException)));

        if (-1 > $err = $state->trySendConnection($quicConnection)) {
            throw new ConnectException("Count not establish connection: $err");
        }

        try {
            $state->startSuspension->suspend();
        } finally {
            EventLoop::cancel($timeout);
            EventLoop::unreference($state->readIds[0]);

            $cancellation?->unsubscribe($cancellationId);
        }

        if ($pingPeriod = $config->getPingPeriod()) {
            $state->pingTimerId = EventLoop::repeat($pingPeriod, function () use ($state) {
                /** @var QuicheConnection $quicConnection */
                $quicConnection = $state->connection->get();
                $quicConnection->ping();
            });
            EventLoop::unreference($state->pingTimerId);
        }

        return $quicConnection;
    }

    private function cancelPing(): void
    {
        if (isset($this->pingTimerId)) {
            EventLoop::cancel($this->pingTimerId);
            unset($this->pingTimerId);
        }
    }

    public function closeConnection(QuicheConnection $connection, bool $applicationError, int $error, string $reason): void
    {
        parent::closeConnection($connection, $applicationError, $error, $reason);
        $this->referenceConnectionInShutdown = $connection;
        $this->cancelPing();
    }

    public function signalConnectionClosed(QuicheConnection $quicConnection): void
    {
        if ($this->startSuspension) {
            $this->startSuspension->throw(new StreamException("Connection attempt rejected"));
            $this->startSuspension = null;
        }
        parent::signalConnectionClosed($quicConnection);
        $this->free();
    }

    protected function free(): void
    {
        parent::free();
        $this->cancelPing();
        unset($this->referenceConnectionInShutdown);
    }

    protected function applyConfig(QuicConfig $config): quiche_config_ptr
    {
        $cfg = parent::applyConfig($config);

        if ($certificate = $config->getCertificate()) {
            self::$quiche->quiche_config_load_cert_chain_from_pem_file($cfg, $certificate->getCertFile());
            self::$quiche->quiche_config_load_priv_key_from_pem_file($cfg, $certificate->getKeyFile());
        }

        return $cfg;
    }

    // No explicit __destruct() here, it's managed through the destructor of QuicheConnection, which will close the connection: signalConnectionClosed() will then eventually free it.
}
