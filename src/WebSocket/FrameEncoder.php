<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\WebSocket;

/**
 * Encodes WebSocket frames. Frames sent by a client must be masked; frames sent
 * by a server must not be.
 */
final class FrameEncoder
{
    public static function encode(string $payload, Opcode $opcode = Opcode::Text, bool $mask = false, bool $fin = true): string
    {
        $byte0 = ($fin ? 0x80 : 0x00) | $opcode->value;
        $length = strlen($payload);

        $header = chr($byte0);
        $maskBit = $mask ? 0x80 : 0x00;

        if ($length <= 125) {
            $header .= chr($maskBit | $length);
        } elseif ($length <= 0xFFFF) {
            $header .= chr($maskBit | 126) . pack('n', $length);
        } else {
            $header .= chr($maskBit | 127) . pack('N', 0) . pack('N', $length);
        }

        if (!$mask) {
            return $header . $payload;
        }

        $key = random_bytes(4);
        $masked = $payload === '' ? '' : $payload ^ substr(str_repeat($key, intdiv($length, 4) + 1), 0, $length);

        return $header . $key . $masked;
    }

    public static function text(string $payload, bool $mask = false): string
    {
        return self::encode($payload, Opcode::Text, $mask);
    }

    public static function binary(string $payload, bool $mask = false): string
    {
        return self::encode($payload, Opcode::Binary, $mask);
    }

    public static function close(int $code = 1000, bool $mask = false): string
    {
        return self::encode(pack('n', $code), Opcode::Close, $mask);
    }

    public static function ping(string $payload = '', bool $mask = false): string
    {
        return self::encode($payload, Opcode::Ping, $mask);
    }

    public static function pong(string $payload = '', bool $mask = false): string
    {
        return self::encode($payload, Opcode::Pong, $mask);
    }
}
