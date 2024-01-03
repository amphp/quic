<?php

use Amp\Socket\ClientTlsContext;
use function Amp\async;

include __DIR__ . "/../vendor/autoload.php";

$tls = (new ClientTlsContext)
    ->withCaFile(__DIR__ . "/../test/ca.crt")
    ->withApplicationLayerProtocols(["test"]);

$client = \Amp\Quic\connect("localhost:1234", $tls);
async(function () use ($client) {
    while ($stream = $client->accept()) {
        async(function () use ($stream) {
            while (null !== $msg = $stream->read()) {
                print "Received from server: $msg\n";
            }
        });

    }
});

async(function () use ($client) {
    while ($datagram = $client->receiveDatagram()) {
        print "Received datagram: $datagram\n";
    }
});

$client->openStream()->write("stream with single message");

$stream = $client->openStream();
$stream->write("parts of ");
$stream->write("a bigger message");

// And now end the stream
\Revolt\EventLoop::delay(1, fn () => $stream->end());

while (null !== $msg = $stream->read()) {
    print "Received from server: $msg\n";
}

$client->close();
