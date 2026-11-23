<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Client;

use Hk100\TcpSocket\Framing\FramingFactory;
use Hk100\TcpSocket\Framing\FramingInterface;

/**
 * Normalised client settings handed to a driver.
 */
final class ClientConfig
{
    /**
     * @param array<string, mixed> $options Driver specific options.
     */
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly int $connectTimeout = 5,
        public readonly string $framingName = 'raw',
        public readonly string $delimiter = "\n",
        public readonly int $lengthBytes = 4,
        public readonly string $path = '/',
        public readonly bool $secure = false,
        public readonly array $options = [],
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function forTcp(array $config): self
    {
        return new self(
            host: (string) ($config['host'] ?? '127.0.0.1'),
            port: (int) ($config['port'] ?? 9501),
            connectTimeout: (int) ($config['connect_timeout'] ?? 5),
            framingName: (string) ($config['framing'] ?? 'eof'),
            delimiter: (string) ($config['delimiter'] ?? "\n"),
            lengthBytes: (int) ($config['length_bytes'] ?? 4),
            options: (array) ($config['options'] ?? []),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function forWebSocket(array $config): self
    {
        $url = (string) ($config['url'] ?? 'ws://127.0.0.1:9502');
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['host'])) {
            throw new \InvalidArgumentException("Invalid WebSocket URL [{$url}].");
        }

        $secure = strtolower($parts['scheme'] ?? 'ws') === 'wss';
        $path = $parts['path'] ?? '/';
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        return new self(
            host: $parts['host'],
            port: (int) ($parts['port'] ?? ($secure ? 443 : 80)),
            connectTimeout: (int) ($config['connect_timeout'] ?? 5),
            path: $path === '' ? '/' : $path,
            secure: $secure,
            options: (array) ($config['options'] ?? []),
        );
    }

    public function framing(): FramingInterface
    {
        return FramingFactory::make($this->framingName, [
            'delimiter' => $this->delimiter,
            'length_bytes' => $this->lengthBytes,
        ]);
    }

    public function tcpUri(): string
    {
        return "tcp://{$this->host}:{$this->port}";
    }

    public function webSocketUri(): string
    {
        $scheme = $this->secure ? 'wss' : 'ws';

        return "{$scheme}://{$this->host}:{$this->port}{$this->path}";
    }
}
