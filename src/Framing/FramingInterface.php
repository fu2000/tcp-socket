<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Framing;

/**
 * Turns a raw TCP byte stream into discrete messages and back.
 *
 * Implementations are stateful per connection: create one instance for each
 * connection (see {@see FramingFactory}).
 */
interface FramingInterface
{
    /**
     * Feed a raw chunk read from the socket and return every message that is
     * now complete. Incomplete trailing bytes are buffered internally.
     *
     * @return list<string>
     */
    public function push(string $chunk): array;

    /**
     * Encode an outbound payload into the bytes that should be written.
     */
    public function encode(string $payload): string;

    public function reset(): void;
}
