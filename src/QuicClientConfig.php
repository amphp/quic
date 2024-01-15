<?php declare(strict_types=1);

namespace Amp\Quic;

use Amp\Socket\Certificate;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;

final class QuicClientConfig extends QuicConfig
{
    private ClientTlsContext $tlsContext;

    private ConnectContext $connectContext;

    private ?string $hostname = null;

    public function __construct(ConnectContext|ClientTlsContext|array $protocolsOrTlsContext)
    {
        parent::__construct();

        $this->connectContext = $this->createConnectContext($protocolsOrTlsContext);
        $this->handshakeTimeout = $this->connectContext->getConnectTimeout();

        $tls = $this->connectContext->getTlsContext();
        if (!$tls?->getApplicationLayerProtocols()) {
            throw new \Error('QUIC requires at least one application layer protocol to be specified.');
        }

        $this->tlsContext = $tls;
    }

    private function createConnectContext(ConnectContext|ClientTlsContext|array $protocolsOrTlsContext): ConnectContext
    {
        if (\is_array($protocolsOrTlsContext)) {
            $tls = (new ClientTlsContext())->withApplicationLayerProtocols($protocolsOrTlsContext);
            return (new ConnectContext())->withTlsContext($tls);
        }

        if ($protocolsOrTlsContext instanceof ClientTlsContext) {
            $tls = $protocolsOrTlsContext;
            return (new ConnectContext())->withTlsContext($tls);
        }

        return $protocolsOrTlsContext;
    }

    public function withHostname(string $hostname): self
    {
        $clone = clone $this;
        $clone->hostname = $hostname;

        return $clone;
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    public function getConnectContext(): ConnectContext
    {
        return $this->connectContext;
    }

    public function getTlsContext(): ClientTlsContext
    {
        return $this->tlsContext;
    }

    public function hasPeerVerification(): bool
    {
        return $this->tlsContext->hasPeerVerification();
    }

    public function getCaPath(): ?string
    {
        return $this->tlsContext->getCaPath();
    }

    public function getCaFile(): ?string
    {
        return $this->tlsContext->getCaFile();
    }

    public function getApplicationLayerProtocols(): array
    {
        return $this->tlsContext->getApplicationLayerProtocols();
    }

    public function getCertificate(): ?Certificate
    {
        return $this->tlsContext->getCertificate();
    }

    public function toStreamContextArray(): array
    {
        return $this->connectContext->withoutTlsContext()->toStreamContextArray();
    }
}
