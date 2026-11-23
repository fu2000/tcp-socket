<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\WebSocket;

/**
 * Client side of the RFC 6455 opening handshake.
 */
final class HandshakeClient
{
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(16));
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    public static function buildRequest(string $host, int $port, string $path, string $key, array $extraHeaders = []): string
    {
        $path = $path === '' ? '/' : $path;
        $hostHeader = $port === 80 || $port === 443 ? $host : "{$host}:{$port}";

        $request = "GET {$path} HTTP/1.1\r\n"
            . "Host: {$hostHeader}\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: {$key}\r\n"
            . "Sec-WebSocket-Version: 13\r\n";

        foreach ($extraHeaders as $name => $value) {
            $request .= "{$name}: {$value}\r\n";
        }

        return $request . "\r\n";
    }

    public static function verifyResponse(string $response, string $key): bool
    {
        if (preg_match('#^HTTP/1\.[01]\s+101#', $response) !== 1) {
            return false;
        }

        $headers = self::parseHeaders($response);
        $accept = $headers['sec-websocket-accept'] ?? '';
        $expected = base64_encode(sha1($key . HandshakeServer::GUID, true));

        return hash_equals($expected, $accept);
    }

    /**
     * @return array<string, string>
     */
    public static function parseHeaders(string $response): array
    {
        $lines = preg_split('/\r\n/', $response) ?: [];
        array_shift($lines);

        $headers = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $headers[$name] = trim(substr($line, $pos + 1));
        }

        return $headers;
    }
}
