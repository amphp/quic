<?php

namespace Amp\Quic;

use Amp\Socket\BindContext;
use Amp\Socket\Certificate;
use Amp\Socket\ServerTlsContext;

class QuicServerConfig extends QuicConfig
{
    private array $context = [];
    private ServerTlsContext $tlsContext;
    private BindContext $bindContext;

    private ?string $statelessResetToken = null;

    public function __construct(ServerTlsContext|BindContext $context)
    {
        if ($context instanceof BindContext) {
            $this->bindContext = $context;
            $this->context = $context->toStreamContextArray();
            $tls = $context->getTlsContext();
            if ($tls === null) {
                throw new \Error('Constructor received a null TLS context. QUIC requires TLS.');
            }
        } else {
            $tls = $context;
        }

        if ($tls->getCertificates()) {
            throw new \Error('QUIC is not able to distinguish between SNI certificates. There must be only one default certificate.');
        }

        $cert = $tls->getDefaultCertificate();
        if (!$cert) {
            throw new \Error('QUIC requires one default certificate.');
        }

        if ($cert->getPassphrase()) {
            throw new \Error('The current QUIC implementation is unable to handle passphrases on certificates.');
        }

        if (!$tls->getApplicationLayerProtocols()) {
            throw new \Error('QUIC requires at least one application layer protocol to be specified.');
        }

        $this->tlsContext = $tls;
    }

    public function getTlsContext(): ServerTlsContext
    {
        return $this->tlsContext;
    }

    public function getBindContext(): BindContext
    {
        return $this->bindContext ??= (new BindContext)->withTlsContext($this->tlsContext);
    }

    public function hasPeerVerification(): bool
    {
        return $this->tlsContext->hasPeerVerification();
    }

    public function toStreamContextArray()
    {
        return $this->context;
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

    public function getCertificate(): Certificate
    {
        return $this->tlsContext->getDefaultCertificate();
    }

    public function withStatelessResetToken(string $token): self
    {
        if (\strlen($token) !== 16) {
            throw new \ValueError("Invalid stateless reset token (" . \bin2hex($token) . "), must exactly 16 bytes");
        }

        $clone = clone $this;
        $clone->statelessResetToken = $token;

        return $clone;
    }

    public function withoutStatelessResetToken(): self
    {
        $clone = clone $this;
        $clone->statelessResetToken = null;

        return $clone;
    }

    public function getStatelessResetToken(): ?string
    {
        return $this->statelessResetToken;
    }

    public function getAcceptQueueSize(): int
    {
        if (isset($this->bindContext)) {
            return $this->bindContext->getBacklog();
        }
        return 128; // default
    }
}
