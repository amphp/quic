<?php declare(strict_types=1);

namespace Amp\Quic;

use Amp\ByteStream\ClosedException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Quic\Pair\PairConnection;

class PairConnectionTest extends AsyncTestCase
{
    public function testCommunication(): void
    {
        [$server, $client] = PairConnection::createPair();

        $serverFuture = \Amp\async(function () use ($server) {
            $server->config = $server->config->withMaxLocalBidirectionalData(10);

            $stream = $server->openStream();
            $stream->write("test");
            $stream->write("buffer");
            $stream->write("exceeding buffer");
            try {
                $stream->write("a lot more here");
            } catch (ClosedException) {
                $this->assertFalse($stream->isWritable());

                $stream = $server->openStream();
                $stream->write("foo");
                $stream->end();
                $this->assertFalse($stream->isWritable());

                return;
            }
            $this->fail("ClosedException not thrown");
        });

        $clientFuture = \Amp\async(function () use ($client) {
            $stream = $client->accept();
            $this->assertSame("test", $stream->read());
            $this->assertSame("buffer", $stream->read());
            $this->assertSame("exceeding ", $stream->read());
            $this->assertSame("buffer", $stream->read());
            $stream->endReceiving();

            $stream = $client->accept();
            $this->assertTrue($stream->isReadable());
            $this->assertSame("foo", $stream->read());
            $this->assertNull($stream->read());
            $this->assertNull($stream->read());
        });

        \Amp\Future\await([$serverFuture, $clientFuture]);
    }

    public function testDatagrams(): void
    {
        [$server, $client] = PairConnection::createPair();

        $serverFuture = \Amp\async(function () use ($server) {
            $server->send("data");
            $server->send("gram");
            $this->assertSame("client", $server->receive());
            $server->close();
            $this->assertNull($server->receive());
        });

        $clientFuture = \Amp\async(function () use ($client) {
            $this->assertSame("data", $client->receive());
            $this->assertSame("gram", $client->receive());
            $client->send("client");
            $this->assertNull($client->receive());
        });

        \Amp\Future\await([$serverFuture, $clientFuture]);
    }

    public function testClose(): void
    {
        [$server, $client] = PairConnection::createPair();
        $server->config = $server->config->withMaxLocalBidirectionalData(10);

        $serverFuture = \Amp\async(function () use ($server) {
            $server->onClose(function () use (&$connCloseInvoked) {
                $connCloseInvoked = true;
            });
            $stream = $server->openStream();
            $stream->onClose(function () use (&$closeInvoked) {
                $closeInvoked = true;
            });
            $stream->write("test");
            try {
                $stream->write("very big string causing stall");
            } catch (ClosedException) {
                $this->assertTrue($stream->isClosed());
                $this->assertTrue($closeInvoked);
                $this->assertTrue($connCloseInvoked);
                return;
            }

            $this->fail("CloseException was not thrown");
        });

        $clientFuture = \Amp\async(function () use ($client) {
            $stream = $client->accept();
            $client->close();
        });

        \Amp\Future\await([$serverFuture, $clientFuture]);
    }
}
