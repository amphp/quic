<?php

namespace Amp\Quic\Internal\Quiche;

use Amp\DeferredFuture;
use Amp\Quic\Bindings\Quiche;
use Amp\Quic\Bindings\quiche_config_ptr;
use Amp\Quic\Bindings\quiche_recv_info_ptr;
use Amp\Quic\Bindings\struct_sockaddr_ptr;
use Amp\Quic\Bindings\uint8_t_ptr;
use Amp\Quic\QuicConfig;
use Amp\Quic\QuicError;
use Amp\Quic\QuicServerConfig;
use Amp\Socket\InternetAddress;
use Amp\Socket\InternetAddressVersion;
use Amp\Socket\SocketAddress;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

/**
 * @psalm-import-type QuicheServerConnection from QuicheConnection
 * @extends QuicheState<false>
 */
class QuicheServerState extends QuicheState
{
    public ?Suspension $acceptor = null;

    /** @var QuicheServerConnection[] */
    public array $acceptQueue = [];

    public int $acceptQueueSize;
    public float $pingPeriod;
    public float $handshakeTimeout;

    /** @var array<QuicheServerConnection|\WeakReference<QuicheServerConnection>> */
    public array $connections = [];
    public ?DeferredFuture $onShutdown = null;

    /** @var resource[] */
    public array $sockets = [];

    /** @var InternetAddress[] */
    public array $localAddresses = [];
    /** @var \Amp\Quic\Bindings\struct_sockaddr_in_ptr[]|\Amp\Quic\Bindings\struct_sockaddr_in6_ptr[] */
    protected array $localSockaddrs = [];

    /** @var \SplPriorityQueue<int, \WeakReference<QuicheServerConnection>> The ping queue is ordered by $pingInsertionTime and guaranteeing that the entry with the pingInsertionTime of the top-most entry is always lower or equal to any $lastReceiveTime. */
    private \SplPriorityQueue $pingQueue;
    private string $pingTimerId;

    /** @param resource[] $sockets */
    public function __construct(array $sockets, QuicServerConfig $config)
    {
        parent::__construct($config);

        $this->acceptQueueSize = $config->getAcceptQueueSize();
        $this->handshakeTimeout = $config->getHandshakeTimeout();
        $this->pingPeriod = $config->getPingPeriod();
        if ($this->pingPeriod) {
            $this->pingQueue = new \SplPriorityQueue;
        }

        foreach ($sockets as $socket) {
            $socketId = (int) $socket;

            $this->installWriteHandler($socket);
            $readId = EventLoop::onReadable($socket, $this->readCallback(...));
            EventLoop::unreference($readId);
            $this->readIds[$socketId] = $readId;

            $localAddress = SocketAddress\fromResourcePeer($socket);
            \assert($localAddress instanceof InternetAddress);
            $this->localAddresses[$socketId] = $localAddress;
            $this->localSockaddrs[$socketId] = self::sockaddrFromInternetAddress($localAddress);

            $this->sockets[$localAddress->toString()] = $socket;
        }
    }

    /** @param resource $socket */
    private function readCallback(string $watcher, $socket): void
    {
        if (false === $buf = \stream_socket_recvfrom($socket, self::MAX_DATAGRAM_SIZE, 0, $sender)) {
            // TODO handle critical local stream error?
            return;
        }

        $socketId = (int) $socket;
        $localSockaddr = $this->localSockaddrs[$socketId];
        $localAddress = $this->localAddresses[$socketId];

        do {
            $colon = \strrpos($sender, ":");
            \assert($colon !== false);
            $port = (int) \substr($sender, $colon + 1);
            if (\strpos($sender, ":") !== $colon) {
                // remove the outer []
                $sockaddr = self::toSockaddr(\inet_pton(\substr($sender, 1, $colon - 2)), $port, InternetAddressVersion::IPv6);
            } else {
                $sockaddr = self::toSockaddr(\inet_pton(\substr($sender, 0, $colon)), $port, InternetAddressVersion::IPv4);
            }

            // dcid: destination connection id
            // scid: source connection id

            $scid = uint8_t_ptr::array(Quiche::QUICHE_MAX_CONN_ID_LEN);
            $scid_len = Quiche::QUICHE_MAX_CONN_ID_LEN;
            $dcid = uint8_t_ptr::array(Quiche::QUICHE_MAX_CONN_ID_LEN);
            $dcid_len = Quiche::QUICHE_MAX_CONN_ID_LEN;
            $token = uint8_t_ptr::array(128);
            $token_len = 128;
            // https://github.com/vimeo/psalm/issues/10551
            /** @psalm-suppress UndefinedVariable */
            if (0 > $success = self::$quiche->quiche_header_info($buf, \strlen($buf), self::LOCAL_CONN_ID_LEN, [&$version], [&$type], $scid, [&$scid_len], $dcid, [&$dcid_len], $token, [&$token_len])) {
                // error, log it? where to? Maybe take a logger?
                // user_error("Failed quiche_header_info: $success");
                return;
            }

            $dcid_string = $dcid->toString($dcid_len);

            $quicConnectionWeak = $this->connections[$dcid_string] ?? null;
            if (!$quicConnectionWeak) {
                if (!self::$quiche->quiche_version_is_supported($version)) {
                    $out = uint8_t_ptr::array(parent::MAX_DATAGRAM_SIZE);
                    $out_len = self::$quiche->quiche_negotiate_version($scid, $scid_len, $dcid, $dcid_len, $out, parent::MAX_DATAGRAM_SIZE);
                    \stream_socket_sendto($socket, $out->toString($out_len), 0, $sender);
                    return;
                }

                $token_prefix = "quiche" . $sender;
                if ($token_len == 0) {
                    // stateless retry
                    $expected_token = $token_prefix . $dcid->toString($dcid_len);
                    $new_cid = \random_bytes(parent::LOCAL_CONN_ID_LEN);
                    $out = uint8_t_ptr::array(parent::MAX_DATAGRAM_SIZE);
                    $out_len = self::$quiche->quiche_retry($scid, $scid_len, $dcid, $dcid_len, $new_cid, self::LOCAL_CONN_ID_LEN, $expected_token, \strlen($expected_token), $version, $out, self::MAX_DATAGRAM_SIZE);
                    \stream_socket_sendto($socket, $out->toString($out_len), 0, $sender);
                    return;
                }

                $token_str = $token->toString($token_len);
                if (!\str_starts_with($token_str, $token_prefix)) {
                    // invalid token
                    return;
                }

                if ((!$this->acceptor && $this->acceptQueueSize <= \count($this->acceptQueue)) || $this->closed) {
                    return;
                }

                // TODO limit pending connections per IP to prevent denial by ram exhaustion - also maybe a global max of connections?

                $original_dcid = \substr($token_str, \strlen($token_prefix));
                if (!$connection = self::$quiche->quiche_accept($dcid, $dcid_len, $original_dcid, \strlen($original_dcid), struct_sockaddr_ptr::castFrom($localSockaddr), Quiche::sizeof($localSockaddr[0]), struct_sockaddr_ptr::castFrom($sockaddr), Quiche::sizeof($sockaddr[0]), $this->quicheConfig)) {
                    return;
                }

                $quicConnection = new QuicheConnection($this, $socket, $localAddress, $localSockaddr, InternetAddress::fromString($sender), $sockaddr, $connection, $dcid_string);
                $quicConnection->establishingTimer = EventLoop::delay($this->handshakeTimeout, $quicConnection->handshakeTimeout(...));
                EventLoop::unreference($quicConnection->establishingTimer);
                $this->connections[$dcid_string] = $quicConnection; // We may not drop the connection before fully establishing it. Keep a proper reference.
            } else {
                $quicConnection = $quicConnectionWeak instanceof QuicheConnection ? $quicConnectionWeak : $quicConnectionWeak->get();
            }
            /**
             * @psalm-var QuicheServerConnection $quicConnection
             * @psalm-var \WeakReference<QuicheServerConnection> $quicConnectionWeak
             */

            $recv_info = quiche_recv_info_ptr::array();
            $recv_info->from = struct_sockaddr_ptr::castFrom($sockaddr);
            $recv_info->from_len = Quiche::sizeof($sockaddr[0]);
            $recv_info->to = struct_sockaddr_ptr::castFrom($localSockaddr);
            $recv_info->to_len = Quiche::sizeof($localSockaddr[0]);
            $success = self::$quiche->quiche_conn_recv($quicConnection->connection, $buf, \strlen($buf), $recv_info);
            // TODO: psalm something is super weird with $quicConnection evolving over the next three psalm-trace calls from Amp\Quic\Internal\Quiche\QuicheConnection<false> to Amp\Quic\Internal\Quiche\QuicheConnection<false>|Amp\Quic\Internal\Quiche\QuicheConnection<bool> and finally to Amp\Quic\Internal\Quiche\QuicheConnection<bool>. Wtf? Similar things happen with the $quicConnectionWeak
            /** @psalm-trace $quicConnection */
            if ($success < 0) {
                // error, log it?
                // user_error("Failed quiche_conn_recv: $success");
                // Note: error -10 is TLS failed, most common error probably
                return;
            }

            /** @psalm-trace $quicConnection */
            if ($this->checkReceive($quicConnection)) {
                /** @psalm-trace $quicConnection */
                if ($quicConnection->establishingTimer !== null) {
                    // But after establishing, we want __destruct() on QuicConnection to properly work
                    $this->connections[$dcid_string] = $quicConnectionWeak = \WeakReference::create($quicConnection);

                    EventLoop::cancel($quicConnection->establishingTimer);
                    $quicConnection->establishingTimer = null;
                    if ($this->acceptor) {
                        $this->acceptor->resume($quicConnection);
                        $this->acceptor = null;
                    } else {
                        if ($this->acceptQueueSize <= \count($this->acceptQueue)) {
                            $quicConnection->close(QuicError::CONNECTION_REFUSED->value);
                        } else {
                            $this->acceptQueue[] = $quicConnection;
                        }
                    }
                }

                if (isset($this->pingQueue)) {
                    $quicConnection->lastReceiveTime = $now = (int) (\microtime(true) * 1e9);
                    if ($quicConnection->pingInsertionTime < 0) {
                        if ($this->pingQueue->isEmpty()) {
                            $this->pingTimerId = EventLoop::delay($this->pingPeriod, $this->sendPings(...));
                            EventLoop::unreference($this->pingTimerId);
                        }
                        $this->pingQueue->insert($quicConnectionWeak, -$now);
                        $quicConnection->pingInsertionTime = $now;
                    } elseif ($quicConnection === $this->pingQueue->top()->get() && $quicConnection->pingInsertionTime < $now - $this->pingPeriod * 5e8) {
                        EventLoop::cancel($this->pingTimerId);
                        /** @var QuicheServerConnection $pingConnection */
                        $pingConnection = $quicConnection;
                        $pingConnectionWeak = $quicConnectionWeak;
                        do {
                            $this->pingQueue->extract();
                            $pingConnection->pingInsertionTime = $pingConnection->lastReceiveTime;
                            $this->pingQueue->insert($pingConnectionWeak, -$pingConnection->lastReceiveTime);
                            while (!($pingConnection = ($pingConnectionWeak = $this->pingQueue->top())->get())) {
                                $this->pingQueue->extract();
                            }
                        } while ($pingConnection->pingInsertionTime !== $pingConnection->lastReceiveTime);

                        $queueTop = $this->pingQueue->top()->get();
                        // TODO: psalm: if I remove this I get PossiblyNullPropertyFetch. If I leave it I get RedundantCondition. Well, thanks?
                        \assert($queueTop !== null);
                        $this->pingTimerId = EventLoop::delay(($queueTop->pingInsertionTime - $now) / 1e9 + $this->pingPeriod, $this->sendPings(...));
                        EventLoop::unreference($this->pingTimerId);
                    }
                }
            }
            $this->checkSend($quicConnection);
        } while (false !== $buf = \stream_socket_recvfrom($socket, self::MAX_DATAGRAM_SIZE, 0, $sender));
    }

    private function sendPings(): void
    {
        $pingCutoff = (\microtime(true) - $this->pingPeriod) * 1e9;
        while (!$this->pingQueue->isEmpty()) {
            $quicConnectionWeak = $this->pingQueue->extract();
            if ($quicConnection = $quicConnectionWeak->get()) {
                if ($quicConnection->lastReceiveTime > $pingCutoff) {
                    $delay = ($quicConnection->pingInsertionTime - $pingCutoff) / 1e9 + $this->pingPeriod;
                    if ($delay <= 0) {
                        $quicConnection->pingInsertionTime = $quicConnection->lastReceiveTime;
                        $this->pingQueue->insert($quicConnectionWeak, -$quicConnection->pingInsertionTime);
                        continue;
                    }
                    $this->pingQueue->insert($quicConnectionWeak, -$quicConnection->pingInsertionTime);
                    $this->pingTimerId = EventLoop::delay($delay, $this->sendPings(...));
                    EventLoop::unreference($this->pingTimerId);
                    return;
                }
                if (!$quicConnection->isClosed()) {
                    $quicConnection->ping();
                    $quicConnection->pingInsertionTime = -1;
                }
            }
        }
    }

    // https://github.com/vimeo/psalm/issues/10552
    /** @psalm-suppress InvalidMethodCall */
    protected function applyConfig(QuicConfig $config): quiche_config_ptr
    {
        $cfg = parent::applyConfig($config);

        $certificate = $config->getCertificate();
        self::$quiche->quiche_config_load_cert_chain_from_pem_file($cfg, $certificate->getCertFile());
        self::$quiche->quiche_config_load_priv_key_from_pem_file($cfg, $certificate->getKeyFile());

        if ($token = $config->getStatelessResetToken()) {
            self::$quiche->quiche_config_set_stateless_reset_token($cfg, $token);
        }

        return $cfg;
    }

    public function close(): void
    {
        if (!$this->connections) {
            parent::close();
        } else {
            $this->closeAcceptor();
        }
    }

    protected function free(): void
    {
        parent::free();
        unset($this->sockets);
        $this->closeAcceptor();
        // Psalm bug: https://github.com/vimeo/psalm/issues/9804
        if ($this->onShutdown?->isComplete() === false) {
            /** @psalm-suppress NullReference */
            $this->onShutdown->complete();
        }
        if ($this->pingPeriod && !$this->pingQueue->isEmpty()) {
            EventLoop::cancel($this->pingTimerId);
        }
    }

    public function closeAcceptor(): void
    {
        if ($this->closed) {
            return;
        }

        $this->acceptQueueSize = 0;
        $this->closed = true;

        $this->acceptor?->resume();
        $this->acceptor = null;
        $this->acceptQueue = [];

        // Psalm bug: https://github.com/vimeo/psalm/issues/9804
        if ($this->onClose?->isComplete() === false) {
            /** @psalm-suppress NullReference */
            $this->onClose->complete();
        }
    }

    public function closeConnection(QuicheConnection $connection, bool $applicationError, int $error, string $reason): void
    {
        parent::closeConnection($connection, $applicationError, $error, $reason);
        // change it to a hard reference to avoid losing context during connection shutdown
        $this->connections[$connection->dcid_string] = $connection;
    }

    public function signalConnectionClosed(QuicheConnection $quicConnection): void
    {
        // TODO: psalm: Why do we have a PossiblyNullArrayOffset here, but not just above in closeConnection where we also access $quicConnection->dcid_string ???
        unset($this->connections[$quicConnection->dcid_string]);
        parent::signalConnectionClosed($quicConnection);

        if ($this->closed && !$this->connections) {
            $this->free();
        }
    }

    /**
     * Automatically cancels the loop watcher.
     */
    public function __destruct()
    {
        if ($this->freed) {
            return;
        }

        if ($this->connections) {
            // This branch is necessary in case the cycle collector decides to first free this object, then the other connections.
            // In any case free() may only be safely called once all connections have been torn down.
            $this->closeAcceptor();
        } else {
            $this->free();
        }
    }
}
