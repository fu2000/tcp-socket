<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Client;

use Hk100\TcpSocket\Contracts\ClientHandlerInterface;
use Hk100\TcpSocket\Contracts\TcpClientInterface;
use Hk100\TcpSocket\Driver\DriverManager;
use Hk100\TcpSocket\Message\Message;

/**
 * Base class for a TCP client. Extend it, override the lifecycle callbacks you
 * need, call configure() with the client config, then connect()/run().
 *
 * <code>
 * class DeviceTcpClient extends AbstractTcpClient
 * {
 *     public function onMessage(Message $message): void
 *     {
 *         // handle $message->payload
 *     }
 * }
 * </code>
 */
abstract class AbstractTcpClient implements ClientHandlerInterface
{
    private ?ClientConfig $config = null;

    private ?TcpClientInterface $transport = null;

    public function configure(ClientConfig $config, ?TcpClientInterface $transport = null): void
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

    public function send(string $data): bool
    {
        return $this->transport()->send($data);
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
        return $this->config ??= ClientConfig::forTcp([]);
    }

    public function transport(): TcpClientInterface
    {
        if ($this->transport === null) {
            $this->transport = (new DriverManager())->tcpClient($this->config());
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
