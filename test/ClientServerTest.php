<?php

// Tests certificate generation:
// openssl genrsa -out ca.pem 4096
// openssl genrsa -out key.pem 4096
// openssl req -x509 -new -nodes -key ca.pem -sha256 -days 3650 -out ca.crt -subj '/CN=amp-quic CA/C=LU/ST=Luxembourg/L=Luxembourg/O=AMPHP'
// openssl req -new -nodes -out cert.csr -newkey rsa:4096 -keyout key.pem -subj '/CN=localhost/C=LU/ST=Luxembourg/L=Luxembourg/O=AMPHP'
// openssl x509 -req -in cert.csr -CA ca.crt -CAkey ca.pem -CAcreateserial -out cert.pem -days 3650 -sha256
// c_rehash .

namespace Amp\Quic;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\StreamException;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Quic\Internal\Quiche\QuicheServerSocket;
use Amp\Quic\Quiche\QuicheStats;
use Amp\Socket\BindContext;
use Amp\Socket\Certificate;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\InternetAddress;
use Amp\Socket\ServerTlsContext;
use Amp\Socket\SocketException;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\Socket\bindUdpSocket;
use function Amp\Socket\SocketAddress\fromResourceLocal;

//define("AMP_QUIC_HEAVY_DEBUGGING", true);

// Implementation note: these tests are mostly testing the interface, but for a few details
// If we're going to implement different QUIC backends, this testsuite should be made a bit more generic and re-used.
class ClientServerTest extends AsyncTestCase
{
    private bool $finished;
    private DeferredFuture $finishedDeferred;

    private int $port = 0;

    /** binds to always the same port */
    protected function bind(string | array $addresses, QuicServerConfig|ServerTlsContext|BindContext $context): QuicheServerSocket
    {
        $fn = function ($address) {
            $colon = \strrpos($address, ":");
            $port = \substr($address, $colon + 1);
            $host = \substr($address, 0, $colon);
            if ($port != 0) {
                return $address;
            }

            if (!$this->port) {

                $srv = \stream_socket_server("udp://$address:0", $errno, $errstr, STREAM_SERVER_BIND);
                /** @var InternetAddress $bound */
                $bound = fromResourceLocal($srv);

                $this->port = $bound->getPort();
            }

            return "$host:{$this->port}";
        };

        if (\is_string($addresses)) {
            $addresses = $fn($addresses);
        } else {
            foreach ($addresses as &$address) {
                $address = $fn($address);
            }
        }

        return bind($addresses, $context);
    }

    protected function spawnEchoServer($alterConfig = null): QuicServerSocket
    {
        $this->finished = false;
        $tls = (new ServerTlsContext)
            ->withDefaultCertificate(new Certificate(__DIR__ . "/cert.pem", __DIR__ . "/key.pem"))
            ->withApplicationLayerProtocols(["test"]);
        $ctx = (new QuicServerConfig($tls))
            ->withMaxRemoteBidirectionalData(5000);
        if ($alterConfig) {
            $ctx = $alterConfig($ctx);
        }
        $server = $this->bind("0.0.0.0:0", $ctx);
        EventLoop::defer(function () use ($server) {
            while ($socket = $server->accept()) {
                EventLoop::queue(function () use ($socket) {
                    while (null !== $data = $socket->read()) {
                        $socket->write($data);
                    }
                    $socket->end();
                });
            }
            $this->finished = true;
        });
        return $server;
    }

    public function testSimple(): void
    {
        $server = $this->spawnEchoServer();

        $tls = (new ClientTlsContext)
            ->withApplicationLayerProtocols(["test"])
            ->withCaFile(__DIR__ . "/ca.crt");
        $client = connect("127.0.0.1:{$this->port}", (new QuicClientConfig($tls))->withHostname("localhost"));
        $socket = $client->openStream();
        $socket->write("test");
        $this->assertSame("test", $socket->read());
        $socket->write("test");
        EventLoop::defer(function () use ($socket) {
            try {
                $socket->read();
                $this->fail("unreachable");
            } catch (PendingReadError) {
            }
        });
        $this->assertSame("test", $socket->read());

        $this->assertTrue($socket->isWritable());
        $socket->end();
        $this->assertFalse($socket->isWritable());
        $this->assertFalse($socket->isClosed());

        $this->assertTrue($socket->isReadable());
        $this->assertNull($socket->read());

        $this->assertFalse($socket->isReadable());
        $this->assertTrue($socket->isClosed());
        $this->assertNull($socket->read()); // Now it is null due closed as UNREADABLE

        $server->close();

        $this->assertNull($client->accept());
        $this->assertTrue($this->finished);
    }

    protected function spawnUdpEchoServer(): QuicServerSocket
    {
        $this->finishedDeferred = new DeferredFuture;
        $tls = (new ServerTlsContext)
            ->withDefaultCertificate(new Certificate(__DIR__ . "/cert.pem", __DIR__ . "/key.pem"))
            ->withApplicationLayerProtocols(["test"]);
        $ctx = (new BindContext)->withTlsContext($tls);
        $server = $this->bind("0.0.0.0:0", $ctx);
        EventLoop::defer(function () use ($server) {
            while ($socket = $server->acceptConnection()) {
                while (null !== $data = $socket->receiveDatagram()) {
                    $socket->sendDatagram($data);
                }
                $this->finishedDeferred->complete();
            }
        });
        return $server;
    }

    public function testDatagramEcho(): void
    {
        $server = $this->spawnUdpEchoServer();
        $client = connect("127.0.0.1:{$this->port}", (new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification());
        $client->sendDatagram("test");
        $this->assertSame("test", $client->receiveDatagram());
        $client->sendDatagram("test");
        $client->sendDatagram("test");
        $this->assertSame("test", $client->receiveDatagram());
        $this->assertSame("test", $client->receiveDatagram());
        $client->close();

        // Await closing
        $this->assertNull($this->finishedDeferred->getFuture()->await());

        $server->close();
    }

    public function testLargeDatagramEcho(): void
    {
        $server = $this->spawnUdpEchoServer();
        $tls = (new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification();
        $cfg = (new QuicClientConfig($tls))->withDatagramSendQueueSize(1);
        $client = connect("127.0.0.1:{$this->port}", $cfg);
        $maxSize = $client->maxDatagramSize();

        $e = null;
        try {
            $client->sendDatagram(\str_repeat("1", $maxSize) . "X"); // one byte too much
        } catch (SocketException $e) {
        }
        $this->assertInstanceOf(SocketException::class, $e);

        $this->assertTrue($client->trySendDatagram("first"));
        $this->assertFalse($client->trySendDatagram("too much"));

        EventLoop::queue($client->sendDatagram(...), "third"); // now a buffered send
        $client->sendDatagram("second"); // incl a queued one

        $this->assertSame("first", $client->receiveDatagram());
        $this->assertSame("second", $client->receiveDatagram());
        $this->assertSame("third", $client->receiveDatagram());
        $client->close();

        // Await closing
        $this->assertNull($this->finishedDeferred->getFuture()->await());

        $server->close();
    }

    protected function spawnMultiStreamServer(): QuicServerSocket
    {
        $this->finished = false;
        $tls = (new ServerTlsContext)
            ->withDefaultCertificate(new Certificate(__DIR__ . "/cert.pem", __DIR__ . "/key.pem"))
            ->withApplicationLayerProtocols(["test"]);
        $ctx = (new QuicServerConfig($tls))
            ->withStatelessResetToken("0123456789abcdef");
        $server = $this->bind("[::]:0", $ctx);
        EventLoop::defer(function () use ($server) {
            $socket = $server->acceptConnection();
            // extra defer to drop $server reference
            EventLoop::defer(function () use ($socket) {
                $streamUni = $socket->openStream();
                $streamUni->endReceiving();

                $streamBidi = $socket->openStream();

                $streamBidi->write("bidi");
                $streamUni->write("uni");

                $streamUni->end();

                $this->assertSame("bidi client", $streamBidi->read());
                $streamBidi->endReceiving();
                $streamBidi->write("closed bidi");
                $streamBidi->end();

                $socket->accept();
                $socket->close();
            });
        });
        return $server;
    }

    public function testServerInitiatedStreams(): void
    {
        $this->spawnMultiStreamServer();
        $client = connect("[::]:{$this->port}", (new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification());
        $streamUni = $client->accept();
        if ($streamUni->id == 3) {
            $streamBidi = $client->accept();
        } else {
            $streamBidi = $streamUni;
            $streamUni = $client->accept();
        }
        $this->assertSame(3, $streamUni->id);
        $this->assertSame(1, $streamBidi->id);

        $this->assertSame("uni", $streamUni->read());
        do {
            try {
                $streamUni->write("fail");
            } catch (ClosedException $e) {
                break;
            }
            $this->fail("Unreachable");
        } while (0);
        $this->assertNull($streamUni->read());

        $streamBidi->write("bidi client");
        $this->assertSame("bidi", $streamBidi->read());

        $this->assertSame("closed bidi", $streamBidi->read());
        do {
            try {
                $streamBidi->write("fail");
            } catch (ClosedException $e) {
                break;
            }
            $this->fail("Unreachable");
        } while (0);

        $this->assertNull($streamBidi->read());

        $stream = $client->openStream();
        $stream->write("client");

        $this->assertNull($client->accept());
    }

    public function testSendFragmentedMessage(): void
    {
        $server = $this->spawnEchoServer();

        $cfg = (new QuicClientConfig((new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification()))
            ->withMaxLocalBidirectionalData(5000);
        $client = connect("127.0.0.1:{$this->port}", $cfg);
        $socket = $client->openStream();
        $str = \implode(\range(1, 1900));
        EventLoop::queue(function () use ($socket, $str) {
            // Many single $socket->write to fully test notifyWritable()
            EventLoop::queue(function () use ($socket, $str) {
                $socket->write($str);
            });
            $socket->write($str);
            $socket->write($str);
        });
        $size = \strlen($str . $str . $str);
        $buf = "";
        for ($i = 0; $i < 4 && \strlen($buf) !== $size; ++$i) {
            $buf .= $socket->read();
        }
        $this->assertSame($str . $str . $str, $buf);
        $socket->end();

        $this->assertNull($socket->read());

        $server->close();
    }

    protected function spawnStreamResetServer(): void
    {
        $this->finished = false;
        $tls = (new ServerTlsContext)
            ->withDefaultCertificate(new Certificate(__DIR__ . "/cert.pem", __DIR__ . "/key.pem"))
            ->withApplicationLayerProtocols(["test"]);
        $server = $this->bind("[::]:0", $tls);
        EventLoop::defer(function () use ($server) {
            $socket = $server->acceptConnection();
            $stream = $socket->accept();
            $stream->resetSending();
        });
    }

    public function testReadingWithStreamReset(): void
    {
        $this->spawnStreamResetServer();
        $client = connect("[::]:{$this->port}", (new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification());

        $stream = $client->openStream();
        $stream->write("start");
        $this->assertNull($stream->read());

        $timeout = EventLoop::delay(2, fn () => $this->fail("timed out"));

        $weak = new \WeakMap;
        $weak[$client] = new class($deferred = new DeferredFuture) {
            public function __construct(public DeferredFuture $deferred)
            {
            }

            public function __destruct()
            {
                $this->deferred->complete();
            }
        };
        unset($stream, $client);

        $deferred->getFuture()->await(); // await draining. This tests whether the cleanup properly works
        EventLoop::cancel($timeout);
    }

    protected function spawnCloseServer(): QuicServerSocket
    {
        $tls = (new ServerTlsContext)
            ->withDefaultCertificate(new Certificate(__DIR__ . "/cert.pem", __DIR__ . "/key.pem"))
            ->withApplicationLayerProtocols(["test"]);
        $cfg = (new QuicServerConfig($tls))
            ->withMaxRemoteBidirectionalData(5000);
        $server = $this->bind("[::]:0", $cfg);
        EventLoop::defer(function () use ($server) {
            $socket = $server->acceptConnection();
            $stream = $socket->accept();
            $this->assertSame($stream->read(), \str_repeat("a", 5000));
            $this->assertSame("datagram", $socket->receiveDatagram());
            $this->assertNull($socket->receiveDatagram());
            $this->assertNull($stream->read());

            $closeReason = $socket->getCloseReason();
            $this->assertSame(QuicError::APPLICATION_ERROR, $closeReason->error);
            $this->assertSame("reason", $closeReason->reason);
        });
        return $server;
    }

    public function testCloseWithPendingWrites(): void
    {
        $server = $this->spawnCloseServer();

        $cfg = (new QuicClientConfig((new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification()))
            ->withMaxLocalBidirectionalData(5000)
            ->withDatagramSendQueueSize(1);
        $client = connect("127.0.0.1:{$this->port}", $cfg);
        $socket = $client->openStream();

        EventLoop::queue(function () use ($socket, &$exWrite) {
            $socket->write(\str_repeat("a", 5000));
            $socket->getConnection()->sendDatagram("datagram");

            try {
                $socket->write(\str_repeat("a", 5000));
            } catch (ClosedException) {
                $exWrite = true;
            }
        });
        EventLoop::queue(function () use ($client, &$exDatagram) {
            try {
                $client->sendDatagram("queued");
            } catch (ClosedException) {
                $exDatagram = true;
            }
        });
        EventLoop::queue(function () use ($client) {
            $client->close(QuicError::APPLICATION_ERROR, "reason");
        });

        \Amp\delay(0);
        $this->assertTrue($exWrite);
        $this->assertTrue($exDatagram);

        $closeReason = $client->getCloseReason();
        $this->assertSame(QuicError::APPLICATION_ERROR, $closeReason->error);
        $this->assertSame("reason", $closeReason->reason);

        $await = new DeferredFuture;
        $server->onShutdown($await->complete(...));
        unset($server);
        $await->getFuture()->await();
    }

    protected function spawnStreamCloseServer(): QuicServerSocket
    {
        $tls = (new ServerTlsContext)
            ->withDefaultCertificate(new Certificate(__DIR__ . "/cert.pem", __DIR__ . "/key.pem"))
            ->withApplicationLayerProtocols(["test"]);
        $cfg = (new QuicServerConfig($tls))
            ->withMaxRemoteBidirectionalData(5000);
        $server = $this->bind(["0.0.0.0:0", "[::]:0"], $cfg);
        EventLoop::defer(function () use ($server) {
            $socket = $server->acceptConnection();
            $stream = $socket->accept();
            $this->assertSame($stream->read(), \str_repeat("a", 5000));
            $stream->endReceiving();
            $this->assertNull($stream->read());

            $stream = $socket->accept();
            $this->assertSame("new stream", $stream->read());
            $socket->close();
        });
        return $server;
    }

    public function testStreamCloseWithPendingWrite(): void
    {
        $server = $this->spawnStreamCloseServer();

        $cfg = (new QuicClientConfig((new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withCaPath(__DIR__)))
            ->withMaxLocalBidirectionalData(5000);
        $client = connect("localhost:{$this->port}", $cfg);
        $socket = $client->openStream();

        $socket->write(\str_repeat("a", 5000));
        try {
            $socket->write(\str_repeat("a", 5000));
        } catch (StreamException $e) {
        }
        $this->assertTrue(isset($e), "StreamException was not thrown");

        $client->openStream()->write("new stream");

        $await = new DeferredFuture;
        $server->onShutdown($await->complete(...));
        $server->close();
        $await->getFuture()->await();
    }

    public function testPriority(): void
    {
        $server = $this->spawnEchoServer();

        $client = connect("127.0.0.1:{$this->port}", (new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification());
        $first = $client->openStream();
        $first->setPriority(255);
        $first->write("first");

        $second = $client->openStream();
        $second->write("second");

        $highPrioRead = \Amp\Future\awaitFirst([$lowPrioRead = \Amp\async($first->read(...)), \Amp\async($second->read(...))]);

        $this->assertSame("second", $highPrioRead);
        $this->assertSame("first", $lowPrioRead->await());

        $first->end();
        $second->end();

        $server->close();

        $await = new DeferredFuture;
        $server->onShutdown($await->complete(...));
        unset($server);
        $await->getFuture()->await();
    }

    public function testTlsInfo(): void
    {
        $server = $this->spawnEchoServer();

        $client = connect("127.0.0.1:{$this->port}", (new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification());
        $socket = $client->openStream();

        $tlsInfo = $socket->getTlsInfo();
        $this->assertSame("TLSv1.3", $tlsInfo->getVersion());
        $this->assertSame("test", $tlsInfo->getApplicationLayerProtocol());
        $this->assertSame("localhost", $tlsInfo->getPeerCertificates()[0]->getSubject()->getCommonName());

        $server->close();
        $await = new DeferredFuture;
        $server->onShutdown($await->complete(...));
        $await->getFuture()->await();
    }

    protected function spawnConnectionSpammedServer(): QuicServerSocket
    {
        $tls = (new ServerTlsContext)
            ->withDefaultCertificate(new Certificate(__DIR__ . "/cert.pem", __DIR__ . "/key.pem"))
            ->withApplicationLayerProtocols(["test"]);
        $cfg = (new BindContext)
            ->withTlsContext($tls)
            ->withBacklog(2);
        $server = $this->bind("[::]:0", $cfg);
        EventLoop::defer(function () use ($server) {
            while ($socket = $server->acceptConnection()) {
                $socket->openStream()->write("success");
                $socket->close();
            }
        });
        return $server;
    }

    public function testConnectionQueuing(): void
    {
        $server = $this->spawnConnectionSpammedServer();

        $batchSend = [];
        $batchId = EventLoop::repeat(0.05, function () use (&$batchSend) {
            foreach ($batchSend as [$target, $datagram]) {
                \stream_socket_sendto($target, $datagram);
            }
            $batchSend = [];
        });

        $successReads = 0;
        for ($i = 0; $i < 5; ++$i) {
            $futures[] = \Amp\async(function () use (&$successReads, &$batchSend) {
                // proxy through another udp server with delay to remove test flakiness
                $intermediary = bindUdpSocket("0.0.0.0:0");
                $target = \stream_socket_client("udp://[::1]:{$this->port}", $errno, $errstr, null, \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT);
                EventLoop::defer(function () use ($intermediary, &$target, &$id, &$address, &$batchSend) {
                    while ([$address, $datagram] = $intermediary->receive()) {
                        $batchSend[] = [$target, $datagram];
                    }
                    EventLoop::cancel($id);
                });
                $id = EventLoop::onReadable($target, function ($watcher, $client) use ($intermediary, &$address) {
                    $intermediary->send($address, \stream_socket_recvfrom($client, 65507));
                });

                $client = connect($intermediary->getAddress(), (new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification());

                if ($stream = $client->accept()) {
                    $stream->read();
                    $this->assertNull($stream->read());
                    ++$successReads;

                    $client->close();
                }

                $intermediary->close();
            });
        }
        \Amp\Future\await($futures);

        // Two are rejected thanks to backlog limit - first is accepted, then two queued
        $this->assertSame(3, $successReads);

        $server->close();
        EventLoop::cancel($batchId);
    }

    public function assertAtMostUnreferenced(int $allowed): void
    {
        $total = 0;
        $unreferenced = 0;
        foreach (EventLoop::getIdentifiers() as $id) {
            if (EventLoop::isEnabled($id)) {
                ++$total;
                $unreferenced += !EventLoop::isReferenced($id);
            }
        }
        $this->assertSame($allowed, $total - $unreferenced);
    }

    public function testUnreferencing(): void
    {
        $server = $this->spawnEchoServer();
        \Amp\delay(0);
        $this->assertAtMostUnreferenced(1);
        $server->unreference();
        $this->assertAtMostUnreferenced(0);

        $client = connect("127.0.0.1:{$this->port}", (new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification());
        $socket = $client->openStream();

        $this->assertAtMostUnreferenced(0);

        $socket->unreference();
        $socket->write("test");
        EventLoop::queue(function () use ($socket) {
            $this->assertAtMostUnreferenced(0);
            $socket->reference();
            $this->assertAtMostUnreferenced(1);
        });
        $this->assertSame("test", $socket->read());

        $socket->write("test");
        EventLoop::queue(function () {
            $this->assertAtMostUnreferenced(2); // now the echo server also holds a reference, due to the open socket
        });
        $this->assertSame("test", $socket->read());

        $this->assertAtMostUnreferenced(1); // only echo server socket
        $client->close();
        $server->close();

        $await = new DeferredFuture;
        $server->onShutdown($await->complete(...));
        $await->getFuture()->await();
    }

    public function testConnectionIdle(): void
    {
        $keylog = __DIR__ . "/keylog.log";
        @\unlink($keylog);

        $server = $this->spawnEchoServer();
        $cfg = (new QuicClientConfig((new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification()))
            ->withIdleTimeout(0.2)
            ->withKeylogFile($keylog);
        $client = connect("127.0.0.1:{$this->port}", $cfg);
        $socket = $client->openStream();

        $time = \microtime(true);

        $socket->write("hey");
        $this->assertSame("hey", $socket->read());

        EventLoop::queue(function () use ($client) {
            \Amp\delay(0.1);
            $client->ping();
            \Amp\delay(0.1);
            $client->ping();
        });

        // let's have the connection time out, then it'll be null
        $this->assertNull($socket->read());
        $this->assertTrue($socket->isClosed());
        $this->assertTrue($client->isClosed());

        // ensure pings actually delay idle
        $this->assertGreaterThan(0.4, \microtime(true) - $time);

        $server->close();

        // The server is already closed: I expect immediate invocation
        $server->onShutdown(function () use (&$called) { $called = true; });
        $this->assertTrue($called);

        // something is written to keylog
        $this->assertFileExists($keylog);
        $this->assertGreaterThan(500, \filesize($keylog));
    }

    public function testConnectionServerPing(): void
    {
        $server = $this->spawnEchoServer(fn (QuicServerConfig $cfg) => $cfg->withPingPeriod(0.1));
        $cfg = (new QuicClientConfig((new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification()))
            ->withIdleTimeout(0.2);
        $client = connect("127.0.0.1:{$this->port}", $cfg);
        $socket = $client->openStream();

        $socket->write("hey");
        $this->assertSame("hey", $socket->read());

        \Amp\delay(0.3);

        // let's verify the hasn't timed out
        $socket->write("still alive");
        $this->assertSame("still alive", $socket->read());

        $client->close();
        $server->close();

        $await = new DeferredFuture;
        $server->onShutdown($await->complete(...));
        $await->getFuture()->await();
    }

    public function testConnectionClientPing(): void
    {
        $server = $this->spawnEchoServer();
        $cfg = (new QuicClientConfig((new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification()))
            ->withIdleTimeout(0.2)
            ->withPingPeriod(0.1);
        $client = connect("127.0.0.1:{$this->port}", $cfg);
        $socket = $client->openStream();

        $time = \microtime(true);

        $socket->write("hey");
        $this->assertSame("hey", $socket->read());

        \Amp\delay(0.3);

        // let's verify the hasn't timed out
        $socket->write("still alive");
        $this->assertSame("still alive", $socket->read());

        $client->close();
        $server->close();

        $await = new DeferredFuture;
        $server->onShutdown($await->complete(...));
        $await->getFuture()->await();
    }

    /**
     * @return array{QuicServerSocket, Future<QuicConnection>}
     * @throws SocketException
     */
    public function spawnMultiInterfaceEchoServer(): array
    {
        $tls = (new ServerTlsContext)
            ->withDefaultCertificate(new Certificate(__DIR__ . "/cert.pem", __DIR__ . "/key.pem"))
            ->withApplicationLayerProtocols(["test"]);
        $server = $this->bind(["0.0.0.0:0", "[::]:0"], $tls);

        $future = async($server->acceptConnection(...));

        $future->map(static function (QuicConnection $socket): void {
            $stream = $socket->accept();
            while (null !== $data = $stream->read()) {
                $stream->write($data);
            }
            $stream->end();
        })->ignore();

        return [$server, $future];
    }

    public function testConnectionMigration(): void
    {
        $srv = $this->spawnMultiInterfaceEchoServer();
        [$server, $future] = $srv;

        // proxy through another udp server to test migration
        $intermediary = bindUdpSocket("0.0.0.0:0");
        $target = \stream_socket_client("udp://[::1]:{$this->port}", $errno, $errstr, null, \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT);
        EventLoop::defer(function () use ($intermediary, &$target, &$id, &$address, &$forwarded) {
            while ([$address, $datagram] = $intermediary->receive()) {
                \stream_socket_sendto($target, $datagram);
                $forwarded?->resume();
                $forwarded = null;
            }
            EventLoop::cancel($id);
        });
        $id = EventLoop::onReadable($target, function ($watcher, $client) use ($intermediary, &$address) {
            $intermediary->send($address, \stream_socket_recvfrom($client, 65507));
        });

        $client = connect($intermediary->getAddress(), (new ClientTlsContext)->withApplicationLayerProtocols(["test"])->withoutPeerVerification());
        $socket = $client->openStream();

        $forwarded = EventLoop::getSuspension();
        $socket->write("hey, ");

        // ensure that it was sent through the old socket
        $forwarded->suspend();

        // replace client socket instance - migrate from ::1 to 127.0.0.1
        $target = \stream_socket_client("udp://127.0.0.1:{$this->port}", $errno, $errstr, null, \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT);
        EventLoop::cancel($id);
        $id = EventLoop::onReadable($target, function ($watcher, $client) use ($intermediary, &$address) {
            $intermediary->send($address, \stream_socket_recvfrom($client, 65507));
        });

        $socket->write("I'm back");

        $this->assertSame("hey, I'm back", $socket->read());

        $serverSocket = $future->await();

        /** @var QuicheStats $stats */
        $stats = $serverSocket->stats();
        $this->assertCount(2, $stats->paths);
        $this->assertSame("[::]:{$this->port}", $stats->paths[0]->localAddr->toString());
        $this->assertSame("0.0.0.0:{$this->port}", $stats->paths[1]->localAddr->toString());
        $this->assertNotSame($stats->paths[0]->peerAddr->toString(), $stats->paths[1]->localAddr->toString());

        $client->close();
        $server->close();

        $await = new DeferredFuture;
        $server->onShutdown($await->complete(...));
        unset($server);
        $await->getFuture()->await();
        EventLoop::cancel($id);
    }

    public function testVersionNegotiation(): void
    {
        $this->spawnEchoServer();
        // bytes 2 to 5 are 0xffffffff, i.e. an unknown version id, everything else is just an arbitrary initial handshake without padding
        $maxVersion = "\xcf\xff\xff\xff\xff\x10\xdb\xf9\x5f\x57\x24\x1c\xe1\x59\xc6\xfa\x94\x12\x41\x08\xb3\x1f\x10\x58\xbb\x65\x15\xd6\x7c\xc4\xe9\x28\xcd\xd8\xa6\xa0\x6f\x97\x18\x00\x41\x08\x1e\xbe\x17\x35\xf3\xd8\x2c\x31\xb1\x3a\x91\x4e\xfe\xee\x5c\x0d\x6e\xa0\x5e\x91\x96\xed\x1f\x5f\xb4\xa7\x6f\xe8\x15\x78\x60\xaf\xde\x04\xcc\x4e\xa6\x0d\x8c\xf1\xe6\xac\x45\x07\xba\x1c\xe0\x05\x24\xfa\x9b\x3e\xc8\xe1\xee\x6b\x5b\x9e\x1c\x38\x93\x23\xe5\xb5\x4a\xf2\xaa\x0d\x2b\xec\x6e\x41\x42\x81\x80\x53\x86\x93\x7c\xc1\xb2\x02\x26\x55\x3b\xbc\xa3\x88\xd8\xdb\xc9\x5e\x61\x45\xeb\xff\x55\x8d\xcc\x26\x85\xca\x70\x5b\x9e\x03\xb1\xaf\xcc\x6f\x51\xe0\x92\x89\x9a\x3a\xd4\x0d\x9a\xab\xd1\xb3\x33\x76\x86\x87\xf9\xa8\x21\x10\x55\x64\x77\xad\xd9\xad\xfe\xd8\x14\x05\xec\xee\xe2\x22\x19\x47\x77\xba\x2e\xfb\xa7\xe5\x76\x40\x99\x4e\x32\x17\xe5\x3a\xca\x55\xab\x9e\x48\x4d\xde\xcd\x00\x38\xce\x98\x38\x18\x05\xf5\xc2\x3e\x9a\x1b\xce\x38\x51\xb8\x5c\xb4\x4f\x2e\xcc\x7e\xd8\x94\x53\x1c\x6a\x76\xe0\x67\x88\x0f\xcf\x78\x01\x95\xf9\xf9\xb5\x09\xa5\xaf\x77\x53\x55\xc2\x4a\xb8\xd1\xae\xee\x4f\x74\xd1\x01\xa4\xb6\x1f\x41\xf5\xa9\x41\x0b\xd9\xd7\xac\x72\x56\x7a\x26\x2e\xf7\xee\xf3\x67\x85\xcd\x77\xa5\xdd\xb1\x34\x56\x95\xa8\xeb\x16\x92\x4f\x2f\x2e\x00\xb8\xc3\x6d\x76";
        $client = \Amp\Socket\connect("udp://127.0.0.1:{$this->port}");
        $client->write($maxVersion);
        $reply = \substr($client->read(), 1, 38);
        $this->assertSame("\x00\x00\x00\x00\x10\x58\xbb\x65\x15\xd6\x7c\xc4\xe9\x28\xcd\xd8\xa6\xa0\x6f\x97\x18\x10\xdb\xf9\x5f\x57\x24\x1c\xe1\x59\xc6\xfa\x94\x12\x41\x08\xb3\x1f", $reply);
    }
}
