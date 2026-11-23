<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Server;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Contracts\WebSocketServerHandlerInterface;
use Hk100\TcpSocket\Message\Message;

/**
 * Base class for WebSocket server business logic.
 */
abstract class AbstractWebSocketServerHandler implements WebSocketServerHandlerInterface
{
    public function onOpen(ConnectionInterface $connection): void
    {
    }

    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
    }

    public function onClose(ConnectionInterface $connection): void
    {
    }

    public function onError(ConnectionInterface $connection, \Throwable $exception): void
    {
    }
}
