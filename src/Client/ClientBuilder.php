<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Client;

use Hk100\TcpSocket\Contracts\TcpClientInterface;
use Hk100\TcpSocket\Contracts\WebSocketClientInterface;
use Hk100\TcpSocket\Driver\DriverManager;

/**
 * Convenience factory that attaches a driver transport to an Abstract*Client
 * subclass using a container-managed {@see DriverManager}.
 */
final class ClientBuilder
{
    public function __construct(private readonly DriverManager $drivers)
    {
    }

    public function tcp(ClientConfig $config, ?string $driver = null): TcpClientInterface
    {
        return $this->drivers->tcpClient($config, $driver);
    }

    public function webSocket(ClientConfig $config, ?string $driver = null): WebSocketClientInterface
    {
        return $this->drivers->webSocketClient($config, $driver);
    }

    public function attachTcp(AbstractTcpClient $client, ClientConfig $config, ?string $driver = null): AbstractTcpClient
    {
        $client->configure($config, $this->drivers->tcpClient($config, $driver));

        return $client;
    }

    public function attachWebSocket(
        AbstractWebSocketClient $client,
        ClientConfig $config,
        ?string $driver = null
    ): AbstractWebSocketClient {
        $client->configure($config, $this->drivers->webSocketClient($config, $driver));

        return $client;
    }
}
