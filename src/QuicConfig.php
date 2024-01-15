<?php

namespace Amp\Quic;

abstract class QuicConfig
{
    // Just have enough initial data. May be lowered on servers with high traffic and low RAM.
    protected int $maxData = 1 << 20;

    protected int $maxLocalBidirectionalData = 1 << 20;

    protected int $maxRemoteBidirectionalData = 1 << 20;

    protected int $maxUnidirectionalData = 1 << 20;

    /**
     * 100 streams seems to generally be a reasonable limit - and it's the lower recommended bound for HTTP/3 streams
     * (RFC 9114 Section 6.1)
     */
    protected int $maxBidirectionalStreams = 100; //

    /** Same then for maxBidirectionalStreams */
    protected int $maxUnidirectionalStreams = 100; //

    /** Balance stale datagrams vs backpressure */
    protected int $datagramReceiveQueueSize = 32;

    /**
     * Doesn't need to be that large, ideally data should be written immediately in a microtask, otherwise the server
     * is seriously overloaded.
     */
    protected int $datagramSendQueueSize = 8;

    protected bool $unidirectionalStreams = true;

    protected bool $bidirectionalStreams = true;

    protected bool $datagrams = true;

    /** A handshake ought to be fast, even with 1-RTT. */
    protected float $handshakeTimeout = 10; //

    /** Given that we default to sending pings after 30 seconds, we'll consider no reply at all as a dead connection. */
    protected float $idleTimeout = 60; //

    /** As recommended in RFC 9000 Section 10.1.2 to avoid losing state in NAT middleboxes. */
    protected float $pingPeriod = 30;

    protected ?string $keylogFile;

    protected function __construct()
    {
        $this->keylogFile = \getenv("SSLKEYLOGFILE") ?: null;
    }

    /** Enables receiving of unidirectional streams - enabled by default. */
    public function withUnidirectionalStreams(): static
    {
        $clone = clone $this;
        $clone->unidirectionalStreams = true;

        return $clone;
    }

    /** Prevents receiving of unidirectional streams. */
    public function withoutUnidirectionalStreams(): static
    {
        $clone = clone $this;
        $clone->unidirectionalStreams = false;

        return $clone;
    }

    /** Whether we accept unidirectional streams initiated by the peer. */
    public function acceptsUnidirectionalStreams(): bool
    {
        return $this->unidirectionalStreams;
    }

    /** Allows sending and receiving bidirectional streams - enabled by default. */
    public function withBidirectionalStreams(): static
    {
        $clone = clone $this;
        $clone->bidirectionalStreams = true;

        return $clone;
    }

    /** Prevents sending and receiving bidirectional streams. */
    public function withoutBidirectionalStreams(): static
    {
        $clone = clone $this;
        $clone->bidirectionalStreams = false;

        return $clone;
    }

    /**
     * Whether we accept and can send bidirectional streams. If this is disabled, any opened stream will be
     * automatically unidirectional.
     */
    public function hasBidirectionalStreams(): bool
    {
        return $this->bidirectionalStreams;
    }

    /** Allows receiving datagrams. */
    public function withDatagrams(): static
    {
        $clone = clone $this;
        $clone->datagrams = true;

        return $clone;
    }

    /** Prevents receiving datagrams. */
    public function withoutDatagrams(): static
    {
        $clone = clone $this;
        $clone->datagrams = false;

        return $clone;
    }

    /** Whether we accept datagrams - enabled by default. */
    public function acceptsDatagrams(): bool
    {
        return $this->datagrams;
    }

    /** Sets the initial global maximum buffered internal data limit for the whole connection, i.e. all streams at once. */
    public function withMaxData(int $bytes): static
    {
        if ($bytes <= 0) {
            throw new \ValueError("Invalid max data limit ({$bytes}), must be greater than 0");
        }

        $clone = clone $this;
        $clone->maxData = $bytes;

        return $clone;
    }

    /** Gets the initial global maximum buffered internal data limit per connection. */
    public function getMaxData(): int
    {
        return $this->maxData;
    }

    /** Sets the initial maximum buffered internal data limit for locally initiated individual bidirectional streams */
    public function withMaxLocalBidirectionalData(int $bytes): static
    {
        if ($bytes <= 0) {
            throw new \ValueError("Invalid local bidirectional max data limit ({$bytes}), must be greater than 0");
        }

        $clone = clone $this;
        $clone->maxLocalBidirectionalData = $bytes;

        return $clone;
    }

    /** Gets the initial maximum data buffered internal limit for locally initiated individual bidirectional streams */
    public function getMaxLocalBidirectionalData(): int
    {
        return $this->maxLocalBidirectionalData;
    }

    /** Sets the initial maximum data buffered internal limit for remotely initiated individual streams */
    public function withMaxRemoteBidirectionalData(int $bytes): static
    {
        if ($bytes <= 0) {
            throw new \ValueError("Invalid remote bidirectional max data limit ({$bytes}), must be greater than 0");
        }

        $clone = clone $this;
        $clone->maxRemoteBidirectionalData = $bytes;

        return $clone;
    }

    /** Gets the initial maximum data buffered internal limit for remotely initiated individual streams */
    public function getMaxRemoteBidirectionalData(): int
    {
        return $this->maxRemoteBidirectionalData;
    }

    /** Sets the limit of remotely initiated bidirectional streams which can be simultaneously open - 100 by default. */
    public function withMaxBidirectionalStreams(int $streams): static
    {
        if ($streams <= 0) {
            throw new \ValueError("Invalid max bidirectional streams limit ({$streams}), must be greater than 0");
        }

        $clone = clone $this;
        $clone->maxBidirectionalStreams = $streams;

        return $clone;
    }

    /** Gets the limit of remotely initiated bidirectional streams which can be simultaneously open */
    public function getMaxBidirectionalStreams(): int
    {
        return $this->maxBidirectionalStreams;
    }

    /** Sets the initial maximum data buffered internal limit for individual unidirectional streams */
    public function withMaxUnidirectionalData(int $bytes): static
    {
        if ($bytes <= 0) {
            throw new \ValueError("Invalid unidirectional max data limit ({$bytes}), must be greater than 0");
        }

        $clone = clone $this;
        $clone->maxUnidirectionalData = $bytes;

        return $clone;
    }

    /** Gets the initial maximum data buffered internal limit for individual unidirectional streams */
    public function getMaxUnidirectionalData(): int
    {
        return $this->maxUnidirectionalData;
    }

    /** Sets the limit of remotely initiated unidirectional streams which can be simultaneously open - 100 by default. */
    public function withMaxUnidirectionalStreams(int $streams): static
    {
        if ($streams <= 0) {
            throw new \ValueError("Invalid max unidirectional streams limit ({$streams}), must be greater than 0");
        }

        $clone = clone $this;
        $clone->maxUnidirectionalStreams = $streams;

        return $clone;
    }

    /** Gets the limit of remotely initiated unidirectional streams which can be simultaneously open */
    public function getMaxUnidirectionalStreams(): int
    {
        return $this->maxUnidirectionalStreams;
    }

    /** Sets the limit of how many datagrams can be buffered at once per connection */
    public function withDatagramReceiveQueueSize(int $datagrams): static
    {
        if ($datagrams <= 0) {
            throw new \ValueError("Invalid datagram receive queue size ({$datagrams}), must be greater than 0");
        }

        $clone = clone $this;
        $clone->datagramReceiveQueueSize = $datagrams;

        return $clone;
    }

    /** Gets the limit of how many datagrams can be buffered at once per connection */
    public function getDatagramReceiveQueueSize(): int
    {
        return $this->datagramReceiveQueueSize;
    }

    /** Sets the limit of how many datagrams can be queued to be sent at once per connection */
    public function withDatagramSendQueueSize(int $datagrams): static
    {
        if ($datagrams <= 0) {
            throw new \ValueError("Invalid datagram send queue size ({$datagrams}), must be greater than 0");
        }

        $clone = clone $this;
        $clone->datagramSendQueueSize = $datagrams;

        return $clone;
    }

    /** Gets the limit of how many datagrams can be queued to be sent at once per connection */
    public function getDatagramSendQueueSize(): int
    {
        return $this->datagramSendQueueSize;
    }

    /** @param float $timeout After how many seconds a connection without any packets received is considered timed
     *      out - defaults to 60 seconds. Set to zero to never timeout.
     */
    public function withIdleTimeout(float $timeout): static
    {
        if ($timeout < 0) {
            throw new \ValueError("Invalid max idle timeout ({$timeout}), must be 0 (disabled) or greater than 0");
        }

        $clone = clone $this;
        $clone->idleTimeout = $timeout;

        return $clone;
    }

    /** @return float After how many seconds a connection without any packets received is considered timed out. */
    public function getIdleTimeout(): float
    {
        return $this->idleTimeout;
    }

    /** @param float $timeout After how many seconds a connection without a completed handshake is considered timed
     *      out - defaults to 10 seconds. For clients this setting is inherited from the
     *      @see ConnectContext::getConnectTimeout()}
     */
    public function withHandshakeTimeout(float $timeout): static
    {
        if ($timeout < 0) {
            throw new \ValueError("Invalid max idle timeout ({$timeout}), must be 0 (disabled) or greater than 0");
        }

        $clone = clone $this;
        $clone->handshakeTimeout = $timeout;

        return $clone;
    }

    /** @return float After how many seconds a connection without a completed handshake is considered timed out. */
    public function getHandshakeTimeout(): float
    {
        return $this->handshakeTimeout;
    }

    /** @param float $seconds How many seconds after the last received UDP packet pertaining to a connection,
     *      an ack-eclicting or ping frame is sent - defaults to 30 seconds. Set to zero to disable.
     */
    public function withPingPeriod(float $seconds): static
    {
        if ($seconds < 0) {
            throw new \ValueError("Invalid max idle timeout ({$seconds}), must be 0 (disabled) or greater than 0");
        }

        $clone = clone $this;
        $clone->pingPeriod = $seconds;

        return $clone;
    }

    /** @return float How many seconds after the last received UDP packet pertaining to a connection, an ack-eclicting
     *      or ping frame is sent.
     */
    public function getPingPeriod(): float
    {
        return $this->pingPeriod;
    }

    /** @param string $path A path to where the keylog files will be created at. May contain %h as a placeholder
     *      for the peer address.
     */
    public function withKeylogFile(string $path): static
    {
        $clone = clone $this;
        $clone->keylogFile = $path;

        return $clone;
    }

    public function withoutKeylogFile(): static
    {
        $clone = clone $this;
        $clone->keylogFile = null;

        return $clone;
    }

    /** @return string|null Path to the keylog file location if active. */
    public function getKeylogFile(): ?string
    {
        return $this->keylogFile;
    }

    /** @return bool Whether peer verification is enabled. Provided by ClientTlsContext or ServerTlsContext. */
    abstract public function hasPeerVerification(): bool;

    /** @return null|string Path to the trusted certificates directory if one is set, otherwise `null`.
     *      Provided by ClientTlsContext or ServerTlsContext.
     */
    abstract public function getCaPath(): ?string;

    /** @return null|string Path to the trusted certificates file if one is set, otherwise `null`.
     *      Provided by ClientTlsContext or ServerTlsContext.
     */
    abstract public function getCaFile(): ?string;

    /** @return string[] Application layer protocols to use. If no protocols match on client or server,
     *      the connection cannot be established. Provided by ClientTlsContext or ServerTlsContext.
     */
    abstract public function getApplicationLayerProtocols(): array;
}
