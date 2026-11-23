<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Message;

/**
 * A single application-level message received from (or sent to) a peer.
 *
 * For TCP transports a message is whatever a {@see \Hk100\TcpSocket\Framing\FramingInterface}
 * yields; for WebSocket transports it is a complete data frame.
 */
final class Message
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $payload,
        public readonly bool $binary = false,
        public readonly ?string $remoteAddress = null,
        public readonly array $meta = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->payload === '';
    }
}
