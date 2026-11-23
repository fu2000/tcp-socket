<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver;

use Hk100\TcpSocket\Client\ClientConfig;
use Hk100\TcpSocket\Contracts\MqttConnectorInterface;
use Hk100\TcpSocket\Contracts\TcpClientInterface;
use Hk100\TcpSocket\Contracts\TcpServerInterface;
use Hk100\TcpSocket\Contracts\WebSocketClientInterface;
use Hk100\TcpSocket\Contracts\WebSocketServerInterface;
use Hk100\TcpSocket\Driver\React\ReactTcpClient;
use Hk100\TcpSocket\Driver\React\ReactTcpServer;
use Hk100\TcpSocket\Driver\React\ReactWebSocketClient;
use Hk100\TcpSocket\Driver\React\ReactWebSocketServer;
use Hk100\TcpSocket\Driver\Support\SwooleSupport;
use Hk100\TcpSocket\Driver\Swoole\SwooleTcpClient;
use Hk100\TcpSocket\Driver\Swoole\SwooleTcpServer;
use Hk100\TcpSocket\Driver\Swoole\SwooleWebSocketClient;
use Hk100\TcpSocket\Driver\Swoole\SwooleWebSocketServer;
use Hk100\TcpSocket\Mqtt\PhpMqttConnector;
use Hk100\TcpSocket\Server\ServerConfig;

/**
 * Resolves a driver and builds servers, clients and connectors.
 */
final class DriverManager
{
    public const DRIVER_AUTO = 'auto';
    public const DRIVER_SWOOLE = 'swoole';
    public const DRIVER_REACT = 'react';

    public function __construct(private readonly string $defaultDriver = self::DRIVER_AUTO)
    {
    }

    /**
     * Resolve "auto" to a concrete driver.
     *
     * For clients, Swoole is only chosen when already inside a coroutine,
     * because its coroutine clients cannot run outside one.
     */
    public function resolve(?string $driver = null, bool $forClient = false): string
    {
        $driver = strtolower(trim($driver ?? $this->defaultDriver));

        if ($driver === '' || $driver === self::DRIVER_AUTO) {
            if (!SwooleSupport::available()) {
                return self::DRIVER_REACT;
            }

            if ($forClient && !SwooleSupport::inCoroutine()) {
                return self::DRIVER_REACT;
            }

            return self::DRIVER_SWOOLE;
        }

        if (!in_array($driver, [self::DRIVER_SWOOLE, self::DRIVER_REACT], true)) {
            throw new \InvalidArgumentException("Unknown driver [{$driver}]. Use auto, swoole or react.");
        }

        return $driver;
    }

    public function tcpServer(ServerConfig $config, ?string $driver = null): TcpServerInterface
    {
        return $this->resolve($driver) === self::DRIVER_SWOOLE
            ? new SwooleTcpServer($config)
            : new ReactTcpServer($config);
    }

    public function webSocketServer(ServerConfig $config, ?string $driver = null): WebSocketServerInterface
    {
        return $this->resolve($driver) === self::DRIVER_SWOOLE
            ? new SwooleWebSocketServer($config)
            : new ReactWebSocketServer($config);
    }

    public function tcpClient(ClientConfig $config, ?string $driver = null): TcpClientInterface
    {
        return $this->resolve($driver, forClient: true) === self::DRIVER_SWOOLE
            ? new SwooleTcpClient($config)
            : new ReactTcpClient($config);
    }

    public function webSocketClient(ClientConfig $config, ?string $driver = null): WebSocketClientInterface
    {
        return $this->resolve($driver, forClient: true) === self::DRIVER_SWOOLE
            ? new SwooleWebSocketClient($config)
            : new ReactWebSocketClient($config);
    }

    public function mqttConnector(): MqttConnectorInterface
    {
        return new PhpMqttConnector();
    }
}
