<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Client;

use Hk100\TcpSocket\Contracts\ClientHandlerInterface;
use Hk100\TcpSocket\Contracts\WebSocketClientInterface;
use Hk100\TcpSocket\Driver\DriverManager;
use Hk100\TcpSocket\Message\Message;

/**
 * Base class for a WebSocket client.
 */
abstract class AbstractWebSocketClient implements ClientHandlerInterface
{
    private ?ClientConfig $config = null;

    private ?WebSocketClientInterface $transport = null;

    public function configure(ClientConfig $config, ?WebSocketClientInterface $transport = null): void
    {
        $this->config = $config;

        if ($transport !== null) {
            $this->transport = $transport;
            $transport->setHandler($this);
        }
    }

    public function connect(): void
    {
        $this->transport()->connect();
    }

    public function run(): void
    {
        $this->transport()->run();
    }

    public function send(string $data, bool $binary = false): bool
    {
        return $this->transport()->send($data, $binary);
    }

    public function close(): void
    {
        $this->transport?->close();
    }

    public function isConnected(): bool
    {
        return $this->transport?->isConnected() ?? false;
    }

    public function config(): ClientConfig
    {
        return $this->config ??= ClientConfig::forWebSocket([]);
    }

    public function transport(): WebSocketClientInterface
    {
        if ($this->transport === null) {
            $this->transport = (new DriverManager())->webSocketClient($this->config());
            $this->transport->setHandler($this);
        }

        return $this->transport;
    }

    public function onConnect(): void
    {
    }

    public function onMessage(Message $message): void
    {
    }

    public function onClose(): void
    {
    }

    public function onError(\Throwable $exception): void
    {
    }
}
