<?php

namespace Amp\Quic\Internal\Quiche;

use Amp\DeferredFuture;
use Amp\Quic\Bindings\Quiche;
use Amp\Quic\Bindings\quiche_config_ptr;
use Amp\Quic\Bindings\quiche_send_info_ptr;
use Amp\Quic\Bindings\QuicheFFI;
use Amp\Quic\Bindings\struct_sockaddr_in;
use Amp\Quic\Bindings\struct_sockaddr_in6;
use Amp\Quic\Bindings\struct_sockaddr_in6_ptr;
use Amp\Quic\Bindings\struct_sockaddr_in_ptr;
use Amp\Quic\Bindings\struct_sockaddr_storage;
use Amp\Quic\Bindings\uint8_t_ptr;
use Amp\Quic\QuicConfig;
use Amp\Socket\InternetAddress;
use Amp\Socket\InternetAddressVersion;
use Revolt\EventLoop;

/**
 * @template TConfigType of QuicConfig
 */
abstract class QuicheState
{
    public static QuicheFFI $quiche;

    public static \WeakMap $configCache;

    /** @var array<int, string> */
    protected array $readIds = [];

    /** @var array<int, string> */
    private array $writeIds = [];

    public ?DeferredFuture $onClose = null;

    protected readonly quiche_config_ptr $quicheConfig;

    protected const MAX_DATAGRAM_SIZE = 1350;
    protected const LOCAL_CONN_ID_LEN = 16;

    // Disabled if no-one is doing any work, unreferenced if all workers are unreferenced
    private int $workingReferences = 0;

    public bool $closed = false;

    public bool $freed = false;

    /** @var array<int, \SplObjectStorage<QuicheConnection<TConfigType>, string>> */
    public array $checkWrites = [];

    public const SEND_BUFFER_SIZE = 65535;

    public static uint8_t_ptr $sendBuffer;

    public static quiche_send_info_ptr $sendInfo;

    /** @var non-empty-string */
    protected readonly string $connectionRandom;

    public readonly ?string $keylogPattern;

    /**
     * @param TConfigType $config
     */
    protected function __construct(
        public readonly QuicConfig $config
    ) {
        $keylogFile = $config->getKeylogFile();
        $this->keylogPattern = $keylogFile ?: null;
        $this->quicheConfig = $this->getConfig($config);
        $this->connectionRandom = \random_bytes(32);
    }

    /** @param $socket resource */
    protected function installWriteHandler(mixed $socket): void
    {
        if (!\is_resource($socket) || \get_resource_type($socket) !== 'stream') {
            throw new \Error('Invalid resource given to constructor!');
        }

        $this->checkWrites[(int) $socket] = new \SplObjectStorage();

        \stream_set_blocking($socket, false);

        $writeId = EventLoop::onWritable($socket, function (string $watcher, $socket) {
            static $errorHandler;
            $errorHandler ??= static function (int $errno, string $errstr): void {
            };

            \set_error_handler($errorHandler);

            $writes = $this->checkWrites[(int) $socket];
            foreach ($writes as $quicConnection) {
                if ("" !== $buf = $writes[$quicConnection]) {
                    if (!\stream_socket_sendto($socket, $buf, 0, $quicConnection->address->toString())) {
                        return;
                    }
                }
                if ($this->trySendConnection($quicConnection) <= 0) {
                    unset($writes[$quicConnection]);
                } else {
                    return;
                }
            }

            \restore_error_handler();

            EventLoop::disable($watcher);
        });
        EventLoop::disable($writeId);
        $this->writeIds[(int) $socket] = $writeId;
    }

    protected static function sockaddrFromInternetAddress(InternetAddress $localAddress
    ): struct_sockaddr_in_ptr|struct_sockaddr_in6_ptr {
        return self::toSockaddr(
            $localAddress->getAddressBytes(),
            $localAddress->getPort(),
            $localAddress->getVersion()
        );
    }

    /**
     * @param TConfigType $config
     */
    private function getConfig(QuicConfig $config): quiche_config_ptr
    {
        if (isset(self::$configCache[$config])) {
            return self::$configCache[$config]->config;
        }

        $cfg = $this->applyConfig($config);
        self::$configCache[$config] = new class($cfg) {
            public function __construct(public quiche_config_ptr $config)
            {
            }

            public function __destruct()
            {
                QuicheState::$quiche->quiche_config_free($this->config);
            }
        };
        return $cfg;
    }

    /**
     * @param TConfigType $config
     */
    protected function applyConfig(QuicConfig $config): quiche_config_ptr
    {
        $cfg = self::$quiche->quiche_config_new(Quiche::QUICHE_PROTOCOL_VERSION);
        \assert($cfg !== null);

        self::$quiche->quiche_config_verify_peer($cfg, (int) $config->hasPeerVerification());
        self::$quiche->quiche_config_set_initial_max_data($cfg, $config->getMaxData());

        if ($config->hasBidirectionalStreams()) {
            self::$quiche->quiche_config_set_initial_max_stream_data_bidi_local(
                $cfg,
                $config->getMaxLocalBidirectionalData()
            );
            self::$quiche->quiche_config_set_initial_max_stream_data_bidi_remote(
                $cfg,
                $config->getMaxRemoteBidirectionalData()
            );
            self::$quiche->quiche_config_set_initial_max_streams_bidi($cfg, $config->getMaxBidirectionalStreams());
        }

        if ($config->acceptsUnidirectionalStreams()) {
            self::$quiche->quiche_config_set_initial_max_stream_data_uni($cfg, $config->getMaxUnidirectionalData());
            self::$quiche->quiche_config_set_initial_max_streams_uni($cfg, $config->getMaxUnidirectionalStreams());
        }

        if ($config->acceptsDatagrams()) {
            self::$quiche->quiche_config_enable_dgram(
                $cfg,
                1,
                $config->getDatagramReceiveQueueSize(),
                $config->getDatagramSendQueueSize()
            );
        }

        if (isset($this->keylogPattern)) {
            self::$quiche->quiche_config_log_keys($cfg);
        }

        $protocols = $config->getApplicationLayerProtocols();
        $protoLen = \array_reduce($protocols, fn ($val, $proto) => $val + \strlen($proto) + 1, 0);
        $protoStr = uint8_t_ptr::array($protoLen);
        $protoIdx = 0;
        foreach ($protocols as $protocol) {
            $protoStr[$protoIdx++] = \strlen($protocol);
            /**
             * @noinspection PhpArithmeticTypeCheckInspection
             * @psalm-suppress InvalidArgument, InvalidOperand
             */
            \FFI::memcpy($protoStr->getData() + $protoIdx, $protocol, \strlen($protocol));
            $protoIdx += \strlen($protocol);
        }

        self::$quiche->quiche_config_set_application_protos($cfg, $protoStr, $protoLen);

        if (null != $caFile = $config->getCaFile()) {
            self::$quiche->quiche_config_load_verify_locations_from_file($cfg, $caFile);
        }

        if (null != $caPath = $config->getCaPath()) {
            self::$quiche->quiche_config_load_verify_locations_from_directory($cfg, $caPath);
        }

        if ($timeout = $config->getIdleTimeout()) {
            self::$quiche->quiche_config_set_max_idle_timeout($cfg, (int) \ceil($timeout * 1000));
        }

        return $cfg;
    }

    protected static function toSockaddr(
        string $addressBytes,
        int $port,
        InternetAddressVersion $version,
    ): struct_sockaddr_in_ptr|struct_sockaddr_in6_ptr {
        $port = (($port & 0xFF) << 8) | ($port >> 8); // network byte order
        if ($version === InternetAddressVersion::IPv6) {
            $sockaddr = struct_sockaddr_in6_ptr::array();
            $sockaddr->sin6_family = STREAM_PF_INET6;
            \FFI::memcpy($sockaddr->sin6_addr->getData(), $addressBytes, 16);
            $sockaddr->sin6_port = $port;
        } else {
            // IPv4
            $sockaddr = struct_sockaddr_in_ptr::array();
            $sockaddr->sin_family = STREAM_PF_INET;
            \FFI::memcpy($sockaddr->sin_addr->getData(), $addressBytes, 4);
            $sockaddr->sin_port = $port;
        }
        return $sockaddr;
    }

    public static function fromSockaddr(struct_sockaddr_storage $sockaddr): InternetAddress
    {
        if ($sockaddr->ss_family === STREAM_PF_INET) {
            $sin6 = struct_sockaddr_in::castFrom($sockaddr);
            $port = $sin6->sin_port;
            $ip = \inet_ntop(\pack("V", $sin6->sin_addr->s_addr));
        } else {
            $sin6 = struct_sockaddr_in6::castFrom($sockaddr);
            $port = $sin6->sin6_port;
            $ip = \inet_ntop(uint8_t_ptr::castFrom($sin6->sin6_addr->addr())->toString(16));
        }

        /** @var int<0, 65535> $port */
        $port = (($port & 0xFF) << 8) | ($port >> 8); // network byte order
        return new InternetAddress($ip, $port);
    }

    private static function compareSockaddr(
        struct_sockaddr_in_ptr|struct_sockaddr_in6_ptr $source,
        struct_sockaddr_storage $sockaddr
    ): bool {
        if ($source instanceof struct_sockaddr_in_ptr) {
            if ($sockaddr->ss_family !== STREAM_PF_INET) {
                return false;
            }

            $sin = struct_sockaddr_in::castFrom($sockaddr);
            return $source->sin_addr->s_addr === $sin->sin_addr->s_addr && $source->sin_port === $sin->sin_port;
        }

        if ($sockaddr->ss_family !== STREAM_PF_INET6) {
            return false;
        }

        $sin6 = struct_sockaddr_in6::castFrom($sockaddr);
        /** @psalm-suppress InvalidPassByReference https://github.com/vimeo/psalm/issues/10549 */
        return \FFI::memcmp($source->sin6_addr->getData(), $sin6->sin6_addr->getData(), 16) === 0
            && $source->sin6_port
            === $sin6->sin6_port;
    }

    /** @param QuicheConnection<TConfigType> $quicConnection */
    protected function trySendConnection(QuicheConnection $quicConnection): int
    {
        while (0 < $size = self::$quiche->quiche_conn_send(
                $quicConnection->connection,
                self::$sendBuffer,
                self::SEND_BUFFER_SIZE,
                self::$sendInfo,
            )
        ) {
            $buf = self::$sendBuffer->toString($size);
            // TODO: Use self::$sendInfo->at
            if ($this instanceof QuicheServerState) {
                // connection migration support: target address may change
                if (!self::compareSockaddr($quicConnection->sockaddr, self::$sendInfo->to)) {
                    // convert back to address
                    $quicConnection->address = self::fromSockaddr(self::$sendInfo->to);
                    $quicConnection->sockaddr = self::sockaddrFromInternetAddress($quicConnection->address);

                    // we'll also assume that the source socket does not change if the IP remains the same (for perf reasons)
                    if (!self::compareSockaddr($quicConnection->localSockaddr, self::$sendInfo->from)) {
                        $address = self::fromSockaddr(self::$sendInfo->from);
                        if ($socket = $this->sockets[$address->toString()] ?? null) {
                            $socketId = (int) $socket;
                            $quicConnection->localSockaddr = $this->localSockaddrs[$socketId];
                            $quicConnection->localAddress = $this->localAddresses[$socketId];
                            $quicConnection->socket = $socket;
                        }
                        // log a debug line maybe, if it wasn't found?
                    }
                }
                $target = $quicConnection->address->toString();
            } else {
                $target = "";
            }
            if (!\stream_socket_sendto($quicConnection->socket, $buf, 0, $target)) {
                /** @noinspection PhpIllegalArrayKeyTypeInspection */
                $this->checkWrites[(int) $quicConnection->socket][$quicConnection] = $buf;
                return 1;
            }
            // error handling is in onwritable handler
        }

        /** @psalm-suppress PossiblyUndefinedVariable https://github.com/vimeo/psalm/issues/10548 */
        return $size;
    }

    /** @param QuicheConnection<TConfigType> $quicConnection */
    private function checkWritable(QuicheConnection $quicConnection): void
    {
        while (0 <= $stream = self::$quiche->quiche_conn_stream_writable_next($quicConnection->connection)) {
            $quicConnection->notifyWritable($stream);
        }
        while ($quicConnection->datagramWrites) {
            $key = \key($quicConnection->datagramWrites);
            [$data, $suspension] = $quicConnection->datagramWrites[$key];
            try {
                if (!$quicConnection->trySend($data)) {
                    break;
                }
                $suspension->resume();
            } catch (\Throwable $e) {
                $suspension->throw($e);
            }
            unset($quicConnection->datagramWrites[$key]);
        }
    }

    /** @param QuicheConnection<TConfigType> $quicConnection */
    public function checkReceive(QuicheConnection $quicConnection): bool
    {
        $conn = $quicConnection->connection;

        if (self::$quiche->quiche_conn_is_closed($conn)) {
            $this->signalConnectionClosed($quicConnection);
            return false;
        }

        if (!self::$quiche->quiche_conn_is_established($conn)) {
            return false;
        }

        while (0 <= $stream = self::$quiche->quiche_conn_stream_readable_next($conn)) {
            $quicConnection->notifyReadable($stream);
        }

        if ($quicConnection->datagramSuspension
            && 0 < $size = self::$quiche->quiche_conn_dgram_recv(
                $conn,
                self::$sendBuffer,
                self::SEND_BUFFER_SIZE
            )) {
            $quicConnection->datagramSuspension->resume(self::$sendBuffer->toString($size));
            $quicConnection->datagramSuspension = null;
        }

        return true;
    }

    /** @param QuicheConnection<TConfigType> $connection */
    public function signalConnectionClosed(QuicheConnection $connection): void
    {
        $connection->notifyClosed();
    }

    /**
     * Always called if connection specific timeouts expire, new packets for the connection or a stream are received or
     * the state of the connection has changed.
     *
     * @param QuicheConnection<TConfigType> $quicConnection
     */
    public function checkSend(QuicheConnection $quicConnection): void
    {
        if ($quicConnection->queuedSend) {
            return;
        }
        $quicConnection->queuedSend = true;

        EventLoop::queue($this->doCheckSend(...), $quicConnection);
    }

    /**
     * checkSend may be called often, basically after every single action, do a queued function to avoid checking too
     * often.
     *
     * @param QuicheConnection<TConfigType> $quicConnection
     */
    private function doCheckSend(QuicheConnection $quicConnection): void
    {
        $quicConnection->queuedSend = false;

        $writes = $this->checkWrites[(int) $quicConnection->socket];
        if ($writes->count()) {
            if (!isset($writes[$quicConnection])) {
                $writes[$quicConnection] = "";
            }
        } else {
            static $errorHandler;
            $errorHandler ??= static function (int $errno, string $errstr): void {
            };

            \set_error_handler($errorHandler);

            if ($this->trySendConnection($quicConnection) > 0) {
                EventLoop::enable($this->writeIds[(int) $quicConnection->socket]);
            }

            \restore_error_handler();

            $this->checkWritable($quicConnection);
        }

        if (self::$quiche->quiche_conn_is_closed($quicConnection->connection)) {
            $this->signalConnectionClosed($quicConnection);
        } else {
            $timeout = self::$quiche->quiche_conn_timeout_as_nanos($quicConnection->connection);
            if ($timeout < 0) {
                $quicConnection->cancelTimer();
            } else {
                $timeout /= 1e9;
                $newTime = \microtime(true) + $timeout;
                if (\abs($quicConnection->nextTimer - $newTime) >= 1e-3) {
                    $quicConnection->cancelTimer();
                    $quicConnection->nextTimer = $newTime;
                    $quicConnectionWeak = \WeakReference::create($quicConnection);
                    $quicConnection->timer = EventLoop::delay($timeout, function () use ($quicConnectionWeak) {
                        /** @var QuicheConnection<TConfigType> $quicConnection */
                        $quicConnection = $quicConnectionWeak->get();
                        self::$quiche->quiche_conn_on_timeout($quicConnection->connection);
                        $quicConnection->nextTimer = 0;
                        $this->checkSend($quicConnection);
                    });
                    EventLoop::unreference($quicConnection->timer);
                }
            }
        }
    }

    /** @param QuicheConnection<TConfigType> $quicConnection */
    public function closeConnection(
        QuicheConnection $quicConnection,
        bool $applicationError,
        int $error,
        string $reason
    ): void {
        // We need to delay this, otherwise we might close the connection before pending writes are submitted
        EventLoop::queue(function () use ($quicConnection, $applicationError, $error, $reason) {
            self::$quiche->quiche_conn_close(
                $quicConnection->connection,
                (int) $applicationError,
                $error,
                $reason,
                \strlen($reason)
            );
            $this->checkSend($quicConnection);
        });
    }

    /**
     * Stops the server/connections completely.
     */
    public function close(): void
    {
        $this->free();
    }

    public function reference(): void
    {
        if ($this->workingReferences++ === 0) {
            EventLoop::reference(\current($this->readIds));
        }
    }

    public function unreference(): void
    {
        if (--$this->workingReferences === 0) {
            EventLoop::unreference(\current($this->readIds));
        }
    }

    protected function free(): void
    {
        foreach ($this->readIds as $readId) {
            EventLoop::cancel($readId);
        }
        foreach ($this->writeIds as $writeId) {
            EventLoop::cancel($writeId);
        }

        $this->freed = true;
    }
}

require_once __DIR__ . "/../../bindings/quiche.php";

QuicheState::$quiche = Quiche::ffi();
QuicheState::$sendBuffer = uint8_t_ptr::array(QuicheState::SEND_BUFFER_SIZE);
QuicheState::$sendInfo = quiche_send_info_ptr::array();
QuicheState::$configCache = new \WeakMap();

if (\defined("AMP_QUIC_HEAVY_DEBUGGING") && \AMP_QUIC_HEAVY_DEBUGGING) {
    /** @noinspection PhpUndefinedMethodInspection */
    QuicheState::$quiche->getFFI()->quiche_enable_debug_logging(function (\FFI\CData $a) {
        print ((new \Amp\Quic\Bindings\string_($a))->toString()) . "\n";
    }, null);
}
