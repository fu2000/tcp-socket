<?php

declare(strict_types=1);

namespace App\Socket;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractTcpServerHandler;

/**
 * Example TCP server handler: echoes every message back with a prefix.
 *
 * Copy into your application and point "tcp-socket.tcp.server.handler" at it.
 */
final class EchoTcpServerHandler extends AbstractTcpServerHandler
{
    public function onOpen(ConnectionInterface $connection): void
    {
        $connection->send("welcome, you are connection #{$connection->id()}");
    }

    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
        $connection->send('echo: ' . $message->payload);
    }

    public function onError(ConnectionInterface $connection, \Throwable $exception): void
    {
        // Log or inspect the failure here.
    }
}
