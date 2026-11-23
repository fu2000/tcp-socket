<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Contracts;

use Hk100\TcpSocket\Message\Message;

/**
 * Business logic for a WebSocket server. Extend
 * {@see \Hk100\TcpSocket\Server\AbstractWebSocketServerHandler} instead of
 * implementing this directly.
 */
interface WebSocketServerHandlerInterface
{
    public function onOpen(ConnectionInterface $connection): void;

    public function onMessage(ConnectionInterface $connection, Message $message): void;

    public function onClose(ConnectionInterface $connection): void;

    public function onError(ConnectionInterface $connection, \Throwable $exception): void;
}
