<?php

use Amp\ByteStream\ClosedException;
use Amp\Quic\QuicError;
use Amp\Socket\Certificate;
use Amp\Socket\ServerTlsContext;
use function Amp\async;

include __DIR__ . "/../vendor/autoload.php";

$tls = (new ServerTlsContext)
    ->withDefaultCertificate(new Certificate(__DIR__ . "/../test/cert.pem", __DIR__ . "/../test/key.pem"))
    ->withApplicationLayerProtocols(["test"]);

$server = \Amp\Quic\bind(["0.0.0.0:1234", "[::]:1234"], $tls);
while ($connection = $server->acceptConnection()) {
    print "Accepted connection from: {$connection->getRemoteAddress()->toString()}\n";

    async(function () use ($connection) {
        $connection->openStream()->write("hey there!");
        $connection->sendDatagram("maybe this arrives?");
        while ($stream = $connection->accept()) {
            async(function () use ($stream) {
                try {
                    while (null !== $msg = $stream->read()) {
                        print "Received msg: $msg\n";
                        $stream->write("Echo: $msg");
                    }
                    $stream->write("Received end");
                } catch (ClosedException) {
                }
            });
            unset($stream); // An unset stream will be automatically closed
        }
    })->catch(fn () => $connection->close(QuicError::APPLICATION_ERROR));
}
