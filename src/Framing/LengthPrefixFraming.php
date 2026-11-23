<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Framing;

/**
 * Length-prefixed framing: an N-byte big-endian unsigned header followed by
 * exactly that many payload bytes.
 */
final class LengthPrefixFraming implements FramingInterface
{
    private string $buffer = '';

    public function __construct(private readonly int $headerBytes = 4)
    {
        if (!in_array($headerBytes, [1, 2, 4], true)) {
            throw new \InvalidArgumentException('Length prefix must be 1, 2 or 4 bytes.');
        }
    }

    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;

        $messages = [];
        while (strlen($this->buffer) >= $this->headerBytes) {
            $length = $this->readLength(substr($this->buffer, 0, $this->headerBytes));
            $total = $this->headerBytes + $length;

            if (strlen($this->buffer) < $total) {
                break;
            }

            $messages[] = substr($this->buffer, $this->headerBytes, $length);
            $this->buffer = substr($this->buffer, $total);
        }

        return $messages;
    }

    public function encode(string $payload): string
    {
        $length = strlen($payload);

        $prefix = match ($this->headerBytes) {
            1 => chr($length & 0xFF),
            2 => pack('n', $length & 0xFFFF),
            4 => pack('N', $length),
        };

        return $prefix . $payload;
    }

    public function reset(): void
    {
        $this->buffer = '';
    }

    private function readLength(string $header): int
    {
        return match ($this->headerBytes) {
            1 => ord($header),
            2 => unpack('n', $header)[1],
            4 => unpack('N', $header)[1],
        };
    }
}
