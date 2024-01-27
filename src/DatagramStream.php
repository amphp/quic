<?php declare(strict_types=1);

namespace Amp\Quic;

use Amp\ByteStream\ClosedException;
use Amp\Cancellation;
use Amp\Socket\PendingReceiveError;

interface DatagramStream
{
    /**
     * Sends a datagram over the connection.
     *
     * @param string $data The data to send. It MUST be smaller than {@see maxDatagramSize()}.
     * @param Cancellation|null $cancellation Abort waiting to send.
     * @throws ClosedException If the connection was closed.
     */
    public function send(string $data, ?Cancellation $cancellation = null): void;

    /**
     * Receives a new datagram.
     *
     * @return string|null Returns {@code null} if the socket is closed.
     *
     * @throws PendingReceiveError If a reception request is already pending.
     */
    public function receive(?Cancellation $cancellation = null): ?string;

    /**
     * @return int The maximum size a single datagram may have.
     */
    public function maxDatagramSize(): int;
}
