<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\WebSocket;

/**
 * Incremental WebSocket frame parser.
 *
 * Server-side parsers require masked frames, client-side parsers require
 * unmasked frames (RFC 6455 §5.1). Fragmented data frames are reassembled and
 * delivered as a single {@see Frame}. Control frames are returned immediately.
 */
final class FrameParser
{
    private string $buffer = '';

    private ?Opcode $fragmentOpcode = null;

    private string $fragmentPayload = '';

    public function __construct(private readonly bool $requireMask = true)
    {
    }

    /**
     * @return list<Frame>
     */
    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;

        $frames = [];
        while (true) {
            $frame = $this->parseOne();
            if ($frame === null) {
                break;
            }
            if ($frame !== false) {
                $frames[] = $frame;
            }
        }

        return $frames;
    }

    public function reset(): void
    {
        $this->buffer = '';
        $this->fragmentOpcode = null;
        $this->fragmentPayload = '';
    }

    /**
     * @return Frame|false|null Frame when complete, false when consumed without
     *                          emitting (partial fragment), null when more data is needed.
     */
    private function parseOne(): Frame|false|null
    {
        $length = strlen($this->buffer);
        if ($length < 2) {
            return null;
        }

        $byte0 = ord($this->buffer[0]);
        $byte1 = ord($this->buffer[1]);

        $fin = ($byte0 & 0x80) !== 0;
        $opcode = Opcode::tryFrom($byte0 & 0x0F);
        if ($opcode === null) {
            throw new WebSocketException('Received a frame with an unknown opcode.');
        }

        $masked = ($byte1 & 0x80) !== 0;
        if ($masked !== $this->requireMask) {
            throw new WebSocketException(
                $this->requireMask
                    ? 'Client-to-server frames must be masked.'
                    : 'Server-to-client frames must not be masked.'
            );
        }

        $payloadLength = $byte1 & 0x7F;
        $offset = 2;

        if ($payloadLength === 126) {
            if ($length < 4) {
                return null;
            }
            $payloadLength = unpack('n', substr($this->buffer, 2, 2))[1];
            $offset = 4;
        } elseif ($payloadLength === 127) {
            if ($length < 10) {
                return null;
            }
            $high = unpack('N', substr($this->buffer, 2, 4))[1];
            $low = unpack('N', substr($this->buffer, 6, 4))[1];
            $payloadLength = ($high << 32) | $low;
            $offset = 10;
        }

        $maskKey = '';
        if ($masked) {
            $offset += 4;
        }

        if ($length < $offset + $payloadLength) {
            return null;
        }

        if ($masked) {
            $maskKey = substr($this->buffer, $offset - 4, 4);
        }

        $payload = substr($this->buffer, $offset, $payloadLength);
        if ($masked) {
            $payload = self::applyMask($payload, $maskKey);
        }

        $this->buffer = substr($this->buffer, $offset + $payloadLength);

        if ($opcode->isControl()) {
            if (!$fin) {
                throw new WebSocketException('Control frames must not be fragmented.');
            }
            return new Frame($opcode, $payload, true);
        }

        return $this->consumeDataFrame($opcode, $payload, $fin);
    }

    private function consumeDataFrame(Opcode $opcode, string $payload, bool $fin): Frame|false
    {
        if ($opcode === Opcode::Continuation) {
            if ($this->fragmentOpcode === null) {
                throw new WebSocketException('Received a continuation frame without a preceding data frame.');
            }
            $this->fragmentPayload .= $payload;
        } else {
            if ($this->fragmentOpcode !== null) {
                throw new WebSocketException('Received a new data frame before the previous one finished.');
            }
            $this->fragmentOpcode = $opcode;
            $this->fragmentPayload = $payload;
        }

        if (!$fin) {
            return false;
        }

        $frame = new Frame($this->fragmentOpcode, $this->fragmentPayload, true);
        $this->fragmentOpcode = null;
        $this->fragmentPayload = '';

        return $frame;
    }

    private static function applyMask(string $payload, string $key): string
    {
        $length = strlen($payload);
        if ($length === 0) {
            return '';
        }

        $repeated = str_repeat($key, intdiv($length, 4) + 1);

        return $payload ^ substr($repeated, 0, $length);
    }
}
