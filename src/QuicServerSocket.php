<?php

namespace Amp\Quic;

use Amp\Cancellation;
use Amp\Socket\PendingAcceptError;
use Amp\Socket\ServerSocket;
use Revolt\EventLoop;

interface QuicServerSocket extends ServerSocket
{
    /**
     * Accepts any stream on any connection.
     * Connections accepted this way are tied to the lifetime of this QuicServerSocket instance.
     *
     * {@inheritDoc}
     */
    public function accept(?Cancellation $cancellation = null): ?QuicSocket;

    /**
     * Unlike {@see accept()}, this method accepts connections directly, on which streams can be opened
     * and datagrams sent.
     *
     * @throws PendingAcceptError If another accept request is pending.
     */
    public function acceptConnection(?Cancellation $cancellation = null): ?QuicConnection;

    /**
     * References the readability callback used for detecting new connection attempts in {@see accept()}.
     *
     * @see EventLoop::reference()
     */
    public function reference(): void;

    /**
     * Unreferences the readability callback used for detecting new connection attempts in {@see accept()}.
     *
     * @see EventLoop::unreference()
     */
    public function unreference(): void;

    /**
     * Gets the underlying resource.
     */
    public function getResource();

    /**
     * Registers a callback that is invoked when the QUIC server is fully shut down.
     *
     * QUIC connections enter a draining phase after shutdown, so that pending frames may still be exchanged during
     * a short window after connection close. The {@see close()} method only sends CLOSE_CONNECTION frames to everyone
     * but does not wait.
     *
     * @param \Closure():void $onShutdown
     */
    public function onShutdown(\Closure $onShutdown): void;
}
