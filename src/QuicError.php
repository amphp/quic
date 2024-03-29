<?php declare(strict_types=1);

namespace Amp\Quic;

enum QuicError: int
{
    case NO_ERROR = 0x00;
    case INTERNAL_ERROR = 0x01;
    case CONNECTION_REFUSED = 0x02;
    case FLOW_CONTROL_ERROR = 0x03;
    case STREAM_LIMIT_ERROR = 0x04;
    case STREAM_STATE_ERROR = 0x05;
    case FRAME_SIZE_ERROR = 0x06;
    case FRAME_ENCODING_ERROR = 0x07;
    case TRANSPORT_PARAMETER_ERROR = 0x08;
    case CONNECTION_ID_LIMIT_ERROR = 0x09;
    case PROTOCOL_VIOLATION = 0x0a;
    case INVALID_TOKEN = 0x0b;
    case APPLICATION_ERROR = 0x0c;
    case CRYPTO_BUFFER_EXCEEDED = 0x0d;
    case KEY_UPDATE_ERROR = 0x0e;
    case AEAD_LIMIT_REACHED = 0x0f;
    case NO_VIABLE_PATH = 0x10;

    // 0x100 plus https://www.iana.org/assignments/tls-parameters/tls-parameters.xhtml#tls-parameters-6
    case TLS_UNEXPECTED_MESSAGE = 0x100 + 10;
    case TLS_BAD_RECORD_MAC = 0x100 + 20;
    case TLS_RECORD_OVERFLOW = 0x100 + 22;
    case TLS_HANDSHAKE_FAILURE = 0x100 + 40;
    case TLS_BAD_CERIFICATE = 0x100 + 42;
    case TLS_UNSUPPORTED_CERTIFICATE = 0x100 + 43;
    case TLS_CERTIFICATE_REVOKED = 0x100 + 44;
    case TLS_CERTIFICATE_EXPIRED = 0x100 + 45;
    case TLS_CERTIFICATE_UNKNOWN = 0x100 + 46;
    case TLS_ILLEGAL_PARAMETER = 0x100 + 47;
    case TLS_UNKNOWN_CA = 0x100 + 48;
    case TLS_ACCESS_DENIED = 0x100 + 49;
    case TLS_DECODE_ERROR = 0x100 + 50;
    case TLS_DECRYPT_ERROR = 0x100 + 51;
    case TLS_TOO_MANY_CIDS_REQUESTED = 0x100 + 52;
    case TLS_PROTOCOL_VERSION = 0x100 + 70;
    case TLS_INSUFFICIENT_SECURITY = 0x100 + 71;
    case TLS_INTERNAL_ERROR = 0x100 + 80;
    case TLS_INAPPROPRIATE_FALLBACK = 0x100 + 86;
    case TLS_USER_CANCELLED = 0x100 + 90;
    case TLS_MISSING_EXTENSION = 0x100 + 109;
    case TLS_UNSUPPORTED_EXTENSION = 0x100 + 110;
    case TLS_UNRECOGNIZED_NAME = 0x100 + 112;
    case TLS_BAD_CERTIFICATE_STATUS_RESPONSE = 0x100 + 113;
    case TLS_UNKNOWN_PSK_IDENTITY = 0x100 + 115;
    case TLS_CERTIFICATE_REQUIRED = 0x100 + 116;
    case TLS_NO_APPLICATION_PROTOCOL = 0x100 + 120;
    case TLS_ECH_REQUIRED = 0x100 + 121;
}
