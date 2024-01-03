<?php

namespace Amp\Quic\Internal\Quiche;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Quic\QuicServerConfig;
use Amp\Quic\QuicServerSocket;
use Amp\Quic\QuicSocket;
use Amp\Socket\BindContext;
use Amp\Socket\InternetAddress;
use Amp\Socket\PendingAcceptError;
use Revolt\EventLoop;

final class QuicheServerSocket implements QuicServerSocket
{
    private readonly QuicheServerState $state;
    private bool $referenced = true;
    private \Closure $cancel;

    /** @var QuicheConnection[] */
    private array $ownedConnections = [];

    /**
     * Should not be called directly, but through the QuicheDriver.
     *
     * @param resource[] $sockets Bound udp socket server resources
     * @param QuicServerConfig $config The constructed server config
     *
     * @throws \Error If a stream resource is not given for $socket.
     */
    public function __construct(
        array $sockets,
        QuicServerConfig $config,
    ) {
        $this->state = new QuicheServerState($sockets, $config);

        $acceptor = &$this->state->acceptor;
        $this->cancel = static function (CancelledException $exception) use (&$acceptor): void {
            $acceptor?->throw($exception);
            $acceptor = null;
        };
    }

    // We actually just return *any* connection awaiting.
    // This mode hides the actual underlying separation of streams, emulating the behaviour of different TCP socket connections.
    public function accept(?Cancellation $cancellation = null): ?QuicSocket
    {
        if ($this->state->acceptor) {
            throw new PendingAcceptError;
        }

        $foundCancellation = new DeferredCancellation();
        $id = $cancellation?->subscribe(function () use ($foundCancellation) {
            $foundCancellation->cancel();
        });

        try {
            foreach (\Amp\Future::iterate((function () use ($foundCancellation) {
                try {
                    $foundCancellation = $foundCancellation->getCancellation();
                    foreach ($this->ownedConnections as $connection) {
                        yield \Amp\async($connection->accept(...), $foundCancellation)->ignore();
                    }
                    while ($connection = $this->acceptConnection($foundCancellation)) {
                        $key = $connection->getRemoteAddress()->toString();
                        $this->ownedConnections[$key] = $connection;
                        $connection->onClose(function () use ($key) { unset($this->ownedConnections[$key]); });
                        yield \Amp\async($connection->accept(...), $foundCancellation)->ignore();
                    }
                } catch (CancelledException) {
                }
            })()) as $future) {
                if ($socket = $future->await()) {
                    $normalExit = true;
                    return $socket;
                }
            }
            $normalExit = true;
            return null;
        } catch (\Throwable $e) {
            $normalExit = true;
            throw $e;
        } finally {
            $cancellation?->unsubscribe($id);
            $foundCancellation->cancel();

            if (!empty($normalExit)) { // catch fiber graceful exit
                // ensure cancellations go through
                $suspension = EventLoop::getSuspension();
                EventLoop::queue($suspension->resume(...));
                $suspension->suspend();
            }
        }
    }

    public function acceptConnection(?Cancellation $cancellation = null): ?\Amp\Quic\QuicConnection
    {
        if ($this->state->acceptor) {
            throw new PendingAcceptError;
        }

        if ($this->state->closed) {
            return null; // Resolve with null when server is closed.
        }

        if ($this->state->acceptQueue) {
            $head = \key($this->state->acceptQueue);
            $value = $this->state->acceptQueue[$head];
            unset($this->state->acceptQueue[$head]);
            return $value;
        }

        $this->state->acceptor = EventLoop::getSuspension();
        $id = $cancellation?->subscribe($this->cancel);

        if ($this->referenced) {
            $this->state->reference();
        }

        try {
            return $this->state->acceptor->suspend();
        } finally {
            if ($this->referenced) {
                $this->state->unreference();
            }

            /** @psalm-suppress PossiblyNullArgument $id is always defined if $cancellation is non-null */
            $cancellation?->unsubscribe($id);
        }
    }

    /**
     * Closes the server and stops accepting connections. Any socket clients accepted will not be closed.
     */
    public function close(): void
    {
        if ($this->state->closed) {
            return;
        }

        foreach ($this->ownedConnections as $connection) {
            $connection->close();
        }
        $this->ownedConnections = [];
        $this->state->close();
        if ($this->state->onShutdown && !$this->state->freed) {
            $this->state->reference();
        }
    }

    public function isClosed(): bool
    {
        return $this->state->closed;
    }

    public function onClose(\Closure $onClose): void
    {
        ($this->state->onClose ??= new DeferredFuture)->getFuture()->finally($onClose);
    }

    public function onShutdown(\Closure $onShutdown): void
    {
        if ($this->state->freed) {
            $onShutdown();
            return;
        }

        if ($this->state->closed && !$this->state->onShutdown) {
            $this->state->reference();
        }
        ($this->state->onShutdown ??= new DeferredFuture)->getFuture()->finally($onShutdown);
    }

    public function reference(): void
    {
        if (!$this->referenced) {
            $this->referenced = true;
            if ($this->state->acceptor) {
                $this->state->reference();
            }
        }
    }

    public function unreference(): void
    {
        if ($this->referenced) {
            $this->referenced = false;
            if ($this->state->acceptor) {
                $this->state->unreference();
            }
        }
    }

    public function getAddress(): InternetAddress
    {
        return \current($this->state->localAddresses);
    }

    public function getBindContext(): BindContext
    {
        return $this->state->config->getBindContext();
    }

    /**
     * Raw stream socket resources.
     *
     * @return resource[]
     */
    public function getResource(): array
    {
        return $this->state->sockets;
    }

    public function __destruct()
    {
        $this->close();
    }
}
