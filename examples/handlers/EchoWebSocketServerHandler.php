<?php

declare(strict_types=1);

namespace App\Socket;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractWebSocketServerHandler;

/**
 * Example WebSocket server handler.
 *
 * Copy into your application and point "tcp-socket.websocket.server.handler"
 * at it.
 */
final class EchoWebSocketServerHandler extends AbstractWebSocketServerHandler
{
    public function onOpen(ConnectionInterface $connection): void
    {
        $connection->send('connected');
    }

    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
        $connection->send('echo: ' . $message->payload);
    }
}
