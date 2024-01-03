<?php

namespace Amp\Quic;

use Amp\Socket\Certificate;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;

class QuicClientConfig extends QuicConfig
{
    private ClientTlsContext $tlsContext;
    private ConnectContext $connectContext;
    private array $context = [];

    private ?string $hostname = null;

    public function __construct(ConnectContext|ClientTlsContext|array $protocolsOrTlsContext)
    {
        if (\is_array($protocolsOrTlsContext)) {
            $tls = (new ClientTlsContext)->withApplicationLayerProtocols($protocolsOrTlsContext);
        } elseif ($protocolsOrTlsContext instanceof ConnectContext) {
            $tls = $protocolsOrTlsContext->getTlsContext() ?? new ClientTlsContext;
            $this->context = $protocolsOrTlsContext->withoutTlsContext()->toStreamContextArray();
            $this->connectContext = $protocolsOrTlsContext;
            $this->handshakeTimeout = $protocolsOrTlsContext->getConnectTimeout();
        } else {
            $tls = $protocolsOrTlsContext;
        }

        if (!$tls->getApplicationLayerProtocols()) {
            throw new \Error('QUIC requires at least one application layer protocol to be specified.');
        }

        $this->tlsContext = $tls;
    }

    public function withHostname(string $hostname): static
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
        return $this->connectContext ?? new ConnectContext;
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
        return $this->context;
    }
}
