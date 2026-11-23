<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Framing;

/**
 * Splits the stream on a delimiter (e.g. "\n" or "\r\n"). The delimiter is
 * not included in the delivered payload and is appended on encode.
 */
final class DelimiterFraming implements FramingInterface
{
    private string $buffer = '';

    public function __construct(private readonly string $delimiter = "\n")
    {
        if ($delimiter === '') {
            throw new \InvalidArgumentException('Delimiter framing requires a non-empty delimiter.');
        }
    }

    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;

        if (!str_contains($this->buffer, $this->delimiter)) {
            return [];
        }

        $parts = explode($this->delimiter, $this->buffer);
        $this->buffer = (string) array_pop($parts);

        return array_values($parts);
    }

    public function encode(string $payload): string
    {
        return $payload . $this->delimiter;
    }

    public function reset(): void
    {
        $this->buffer = '';
    }
}
