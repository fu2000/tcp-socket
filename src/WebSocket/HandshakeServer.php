<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\WebSocket;

/**
 * Server side of the RFC 6455 opening handshake.
 */
final class HandshakeServer
{
    public const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /**
     * Build the "101 Switching Protocols" response, or null when the request is
     * not a valid WebSocket upgrade.
     */
    public static function buildAcceptResponse(string $requestHeaders): ?string
    {
        $headers = self::parseHeaders($requestHeaders);

        $upgrade = strtolower($headers['upgrade'] ?? '');
        $connection = strtolower($headers['connection'] ?? '');
        $key = $headers['sec-websocket-key'] ?? '';
        $version = $headers['sec-websocket-version'] ?? '';

        if ($upgrade !== 'websocket' || !str_contains($connection, 'upgrade')) {
            return null;
        }

        if ($key === '' || $version !== '13') {
            return null;
        }

        $accept = base64_encode(sha1($key . self::GUID, true));

        return "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: {$accept}\r\n"
            . "\r\n";
    }

    public static function buildRejectResponse(int $status = 400, string $reason = 'Bad Request'): string
    {
        $body = $reason;

        return "HTTP/1.1 {$status} {$reason}\r\n"
            . "Content-Type: text/plain\r\n"
            . "Content-Length: " . strlen($body) . "\r\n"
            . "Connection: close\r\n"
            . "\r\n"
            . $body;
    }

    public static function getPath(string $request): ?string
    {
        if (preg_match('#^GET\s+(\S+)\s+HTTP/#', $request, $matches) !== 1) {
            return null;
        }

        $path = $matches[1];
        $queryPos = strpos($path, '?');

        return $queryPos === false ? $path : substr($path, 0, $queryPos);
    }

    /**
     * @return array<string, string>
     */
    public static function parseHeaders(string $request): array
    {
        $lines = preg_split('/\r\n/', $request) ?: [];
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
