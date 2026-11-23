<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Framing;

/**
 * Treats every chunk read from the socket as a complete message. Suitable when
 * the device protocol sends self-delimiting packets small enough not to be
 * split across reads.
 */
final class RawFraming implements FramingInterface
{
    public function push(string $chunk): array
    {
        return $chunk === '' ? [] : [$chunk];
    }

    public function encode(string $payload): string
    {
        return $payload;
    }

    public function reset(): void
    {
    }
}
