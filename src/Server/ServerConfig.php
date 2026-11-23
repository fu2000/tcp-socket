<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Server;

use Hk100\TcpSocket\Framing\FramingFactory;
use Hk100\TcpSocket\Framing\FramingInterface;

/**
 * Normalised server settings handed to a driver.
 */
final class ServerConfig
{
    /**
     * @param array<string, mixed> $options Driver specific options (e.g. Swoole\Server::set()).
     */
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $path = '/',
        public readonly string $framingName = 'raw',
        public readonly string $delimiter = "\n",
        public readonly int $lengthBytes = 4,
        public readonly array $options = [],
        public readonly int $heartbeatInterval = 0,
        public readonly int $heartbeatTimeout = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     * @return array{0: int, 1: int} [interval, timeout]; both 0 when disabled.
     */
    private static function heartbeat(array $config): array
    {
        $heartbeat = $config['heartbeat'] ?? null;
        if (!is_array($heartbeat)) {
            return [0, 0];
        }

        $interval = (int) ($heartbeat['interval'] ?? 30);
        $timeout = (int) ($heartbeat['timeout'] ?? 0);
        if ($interval <= 0) {
            $interval = 30;
        }
        if ($timeout <= 0) {
            $timeout = $interval * 3;
        }

        return [$interval, $timeout];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function forTcp(array $config): self
    {
        [$heartbeatInterval, $heartbeatTimeout] = self::heartbeat($config);

        return new self(
            host: (string) ($config['host'] ?? '0.0.0.0'),
            port: (int) ($config['port'] ?? 9501),
            framingName: (string) ($config['framing'] ?? 'eof'),
            delimiter: (string) ($config['delimiter'] ?? "\n"),
            lengthBytes: (int) ($config['length_bytes'] ?? 4),
            options: (array) ($config['options'] ?? []),
            heartbeatInterval: $heartbeatInterval,
            heartbeatTimeout: $heartbeatTimeout,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function forWebSocket(array $config): self
    {
        [$heartbeatInterval, $heartbeatTimeout] = self::heartbeat($config);

        return new self(
            host: (string) ($config['host'] ?? '0.0.0.0'),
            port: (int) ($config['port'] ?? 9502),
            path: (string) ($config['path'] ?? '/'),
            options: (array) ($config['options'] ?? []),
            heartbeatInterval: $heartbeatInterval,
            heartbeatTimeout: $heartbeatTimeout,
        );
    }

    public function framing(): FramingInterface
    {
        return FramingFactory::make($this->framingName, [
            'delimiter' => $this->delimiter,
            'length_bytes' => $this->lengthBytes,
        ]);
    }
}
