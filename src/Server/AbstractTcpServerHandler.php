<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Server;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Contracts\TcpServerHandlerInterface;
use Hk100\TcpSocket\Message\Message;

/**
 * Base class for TCP server business logic. Override only the callbacks you
 * need, then point the "tcp.server.handler" config at your subclass.
 */
abstract class AbstractTcpServerHandler implements TcpServerHandlerInterface
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
